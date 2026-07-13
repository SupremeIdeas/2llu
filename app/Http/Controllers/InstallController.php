<?php

namespace App\Http\Controllers;

use App\Support\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Web installer wizard (blueprint Section 22.1): requirements → database →
 * application → provider keys → finalize. Guarded by EnsureNotInstalled so it
 * only runs until the lock file is written.
 */
class InstallController extends Controller
{
    public function requirements()
    {
        return view('install.requirements', [
            'requirements' => Installer::requirements(),
            'pass' => Installer::requirementsPass(),
        ]);
    }

    public function database()
    {
        return view('install.database');
    }

    public function storeDatabase(Request $request)
    {
        $data = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        session(['install.db' => $data]);

        return redirect('/install/application');
    }

    public function application()
    {
        return view('install.application');
    }

    public function storeApplication(Request $request)
    {
        $data = $request->validate([
            'app_name' => 'required|string',
            'app_url' => 'required|url',
            'admin_name' => 'required|string',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8',
        ]);

        session(['install.app' => $data]);

        return redirect('/install/providers');
    }

    public function providers()
    {
        return view('install.providers');
    }

    public function finalize(Request $request)
    {
        $app = session('install.app');
        if (! $app) {
            return redirect('/install/application');
        }
        $db = session('install.db', []);

        // 1) Write .env from the collected settings + provider keys.
        $env = array_filter([
            'APP_NAME' => $app['app_name'],
            'APP_URL' => $app['app_url'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['db_host'] ?? null,
            'DB_PORT' => $db['db_port'] ?? null,
            'DB_DATABASE' => $db['db_database'] ?? null,
            'DB_USERNAME' => $db['db_username'] ?? null,
            'DB_PASSWORD' => $db['db_password'] ?? null,
        ], fn ($v) => $v !== null);

        if (! config('app.key')) {
            $env['APP_KEY'] = Installer::generateAppKey();
        }

        // Provider keys arrive as key_ESIMGO_API_KEY etc.; blanks are skipped
        // so the product shows "Coming Soon" until a real key is saved.
        foreach ($request->except('_token') as $field => $value) {
            if (str_starts_with($field, 'key_') && $value !== null && $value !== '') {
                $env[strtoupper(substr($field, 4))] = $value;
            }
        }

        Installer::writeEnv($env);

        // 2) Point the DB connection at the new database, then migrate + seed.
        if (! app()->runningUnitTests() && ! empty($db)) {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $db['db_host'],
                'database.connections.mysql.port' => $db['db_port'],
                'database.connections.mysql.database' => $db['db_database'],
                'database.connections.mysql.username' => $db['db_username'],
                'database.connections.mysql.password' => $db['db_password'] ?? '',
            ]);
            DB::purge('mysql');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        // 3) Create the super_admin.
        $admin = Installer::createAdmin($app['admin_name'], $app['admin_email'], $app['admin_password']);

        // 4) Lock the installer and warm the caches.
        Installer::markInstalled();
        if (! app()->runningUnitTests()) {
            foreach (['config:cache', 'route:cache', 'view:cache', 'icons:cache'] as $command) {
                Artisan::call($command);
            }
        }

        session()->forget(['install.db', 'install.app']);

        return redirect('/login')->with('status', 'Installation complete. Sign in with your admin account.');
    }
}

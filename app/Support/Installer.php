<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Web installer core (blueprint Section 22.1). Drives the CodeCanyon-style
 * flow: requirements → DB → app → keys → finalize, guarded by an `installed`
 * lock file so /install is a one-time wizard. Provider keys left blank simply
 * show "Coming Soon" until filled (Section 17.4) — no code change to go live.
 */
class Installer
{
    /** Overridable in tests so we never clobber the real .env. */
    public static ?string $envPath = null;

    public static function envPath(): string
    {
        return self::$envPath ?? base_path('.env');
    }

    public static function lockPath(): string
    {
        return storage_path('installed');
    }

    public static function isInstalled(): bool
    {
        return file_exists(self::lockPath());
    }

    public static function markInstalled(): void
    {
        @file_put_contents(self::lockPath(), 'installed '.now()->toDateTimeString().PHP_EOL);
    }

    public static function unlock(): void
    {
        if (file_exists(self::lockPath())) {
            @unlink(self::lockPath());
        }
    }

    /**
     * Server requirements. Each row: label, ok, required.
     *
     * @return array<int, array{label: string, ok: bool, required: bool}>
     */
    public static function requirements(): array
    {
        $ext = fn (string $e) => extension_loaded($e);

        return [
            ['label' => 'PHP 8.2 or higher ('.PHP_VERSION.')', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'required' => true],
            ['label' => 'PDO extension', 'ok' => $ext('pdo'), 'required' => true],
            ['label' => 'mbstring extension', 'ok' => $ext('mbstring'), 'required' => true],
            ['label' => 'openssl extension', 'ok' => $ext('openssl'), 'required' => true],
            ['label' => 'curl extension', 'ok' => $ext('curl'), 'required' => true],
            ['label' => 'gd extension', 'ok' => $ext('gd'), 'required' => true],
            ['label' => 'zip extension', 'ok' => $ext('zip'), 'required' => true],
            ['label' => 'Redis extension (recommended)', 'ok' => $ext('redis'), 'required' => false],
            ['label' => 'storage/ is writable', 'ok' => is_writable(storage_path()), 'required' => true],
            ['label' => 'bootstrap/cache/ is writable', 'ok' => is_writable(base_path('bootstrap/cache')), 'required' => true],
        ];
    }

    public static function requirementsPass(): bool
    {
        foreach (self::requirements() as $req) {
            if ($req['required'] && ! $req['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge KEY=value pairs into the .env file (creating it from .env.example
     * when absent). Values containing spaces/# are quoted.
     *
     * @param  array<string, string>  $values
     */
    public static function writeEnv(array $values): void
    {
        $path = self::envPath();
        $contents = file_exists($path)
            ? file_get_contents($path)
            : (file_exists(base_path('.env.example')) ? file_get_contents(base_path('.env.example')) : '');

        foreach ($values as $key => $value) {
            $line = $key.'='.self::escape((string) $value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents);
    }

    private static function escape(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }

    public static function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    // ---- Cron / scheduler setup (blueprint Section 20) ----------------------

    /** True when this install runs the VPS profile (Redis queue + Horizon). */
    public static function isVps(): bool
    {
        return config('queue.default') !== 'database';
    }

    /** Best-guess absolute path to the PHP CLI binary for the cron line. */
    public static function phpBinary(): string
    {
        // PHP_BINARY under a web SAPI can be php-fpm; fall back to a plain "php"
        // which cPanel's cron UI resolves to the account's selected version.
        $bin = PHP_BINARY ?: 'php';
        if (str_contains($bin, 'fpm') || str_contains($bin, 'apache')) {
            return 'php';
        }

        return $bin;
    }

    /**
     * THE one cron entry the operator must add. This single line drives the whole
     * platform: the scheduler (health checks, catalogue sync, backups) AND — on
     * shared/cPanel — draining the queue every minute. Runs `schedule:run` each
     * minute, exactly as Laravel expects.
     */
    public static function cronLine(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: base_path();

        return '* * * * * cd '.$appPath.' && '.$php.' artisan schedule:run >> /dev/null 2>&1';
    }

    /** cPanel's cron UI splits schedule + command; give the command half too. */
    public static function cronCommandOnly(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: base_path();

        return 'cd '.$appPath.' && '.$php.' artisan schedule:run >> /dev/null 2>&1';
    }

    /** VPS-only: the long-running queue worker to run under a supervisor/Horizon. */
    public static function queueWorkerCommand(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: base_path();

        return $php.' '.$appPath.'/artisan horizon';
    }

    /** Create the first super_admin (blueprint Section 22.1 step 3). */
    public static function createAdmin(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'is_active' => true,
            'referral_code' => Str::upper(Str::random(8)),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super_admin');

        return $user;
    }
}

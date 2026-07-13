<x-layouts.app title="Install — Database">
    <x-install-shell :step="2">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-slate-100">Database connection</h2>
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/install/database" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Host</label>
                    <input name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Port</label>
                    <input name="db_port" value="{{ old('db_port', '3306') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database name</label>
                <input name="db_database" value="{{ old('db_database', 'naarasim') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Username</label>
                    <input name="db_username" value="{{ old('db_username', 'root') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
                    <input type="password" name="db_password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <div class="flex justify-between">
                <a href="/install" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:border-[#2D4060] dark:text-slate-300">Back</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">Continue <x-icon name="chevron-right" class="h-4 w-4" /></button>
            </div>
        </form>
    </x-install-shell>
</x-layouts.app>

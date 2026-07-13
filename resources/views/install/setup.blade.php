<x-layouts.app title="Install — Database Setup">
    <x-install-shell :step="3">
        <h2 class="mb-4 text-center text-lg font-bold text-slate-900 dark:text-slate-100">Setup</h2>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="/install/setup" x-data="{ tab: 'environment' }">
            @csrf

            {{-- Tabs --}}
            <div class="mb-6 flex items-center justify-center gap-2">
                <button type="button" @click="tab = 'environment'"
                        :class="tab === 'environment' ? 'bg-slate-100 dark:bg-[#243352] text-slate-900 dark:text-slate-100' : 'text-slate-500'"
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold">Environment</button>
                <button type="button" @click="tab = 'database'"
                        :class="tab === 'database' ? 'bg-slate-100 dark:bg-[#243352] text-slate-900 dark:text-slate-100' : 'text-slate-500'"
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold">Database</button>
            </div>

            {{-- Environment tab --}}
            <div x-show="tab === 'environment'" class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">App Name</label>
                    <input name="app_name" value="{{ old('app_name', 'NaaraSim') }}" placeholder="App Name" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">App URL</label>
                    <input name="app_url" value="{{ old('app_url', 'https://') }}" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        Please do not enter “/” at the end of the URL. Example: https://naarasim.com
                    </p>
                </div>
                <button type="button" @click="tab = 'database'"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-3 font-semibold text-slate-900 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-100 dark:hover:bg-[#243352]">
                    Setup Database <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
            </div>

            {{-- Database tab --}}
            <div x-show="tab === 'database'" x-cloak class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database Connection</label>
                    <select name="db_connection" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="mysql">mysql</option>
                        <option value="sqlite">sqlite</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database Host</label>
                    <input name="db_host" value="{{ old('db_host', '127.0.0.1') }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database Port</label>
                    <input name="db_port" value="{{ old('db_port', '3306') }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database Name</label>
                    <input name="db_database" value="{{ old('db_database', 'naarasim') }}" placeholder="Database Name" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database User Name</label>
                    <input name="db_username" value="{{ old('db_username', 'root') }}" placeholder="Database User Name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Database Password</label>
                    <input type="password" name="db_password" placeholder="Database Password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white hover:bg-primary-dark">
                    Install <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <a href="/install/requirements" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
            </a>
        </div>
    </x-install-shell>
</x-layouts.app>

<x-layouts.app title="Install — Application">
    <x-install-shell :step="3">
        <h2 class="mb-4 text-lg font-bold text-slate-900 dark:text-slate-100">Application &amp; admin account</h2>
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="/install/application" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">App name</label>
                    <input name="app_name" value="{{ old('app_name', 'NaaraSim') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">App URL</label>
                    <input name="app_url" value="{{ old('app_url', 'https://') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <hr class="border-slate-100 dark:border-[#243352]">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Admin name</label>
                <input name="admin_name" value="{{ old('admin_name') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Admin email</label>
                    <input type="email" name="admin_email" value="{{ old('admin_email') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Admin password</label>
                    <input type="password" name="admin_password" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <div class="flex justify-between">
                <a href="/install/database" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:border-[#2D4060] dark:text-slate-300">Back</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">Continue <x-icon name="chevron-right" class="h-4 w-4" /></button>
            </div>
        </form>
    </x-install-shell>
</x-layouts.app>

<x-layouts.app title="Create account — NaaraSim">
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <p class="text-xs font-semibold uppercase tracking-widest text-accent">Supreme Ideas Agency</p>
                <h1 class="mt-1 text-2xl font-bold text-primary-dark dark:text-primary">Join NaaraSim</h1>
            </div>

            <form method="POST" action="/register"
                  class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @csrf
                @if ($errors->any())
                    <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <x-auth.google-button />
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Confirm password</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="badge-check" class="h-5 w-5" /> Create account
                </button>
                <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                    Already have an account? <a href="/login" class="font-semibold text-primary hover:underline">Sign in</a>
                </p>
            </form>
        </div>
    </main>
</x-layouts.app>

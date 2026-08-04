<div class="mx-auto max-w-4xl">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">System health</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Is the live cron firing and the queue draining? If a task is overdue, fix the cPanel cron — not the code.</p>
        </div>
        <button type="button" wire:click="refreshHealth" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
            <x-icon name="refresh" class="mr-1 inline h-4 w-4" /> Refresh
        </button>
    </div>

    {{-- Headline verdict --}}
    @if ($anyOverdue || $queueIsSync)
        <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 p-5 dark:border-red-900/50 dark:bg-red-950/30">
            <p class="flex items-center gap-2 font-semibold text-red-700 dark:text-red-300">
                <x-icon name="info" class="h-5 w-5" /> Something needs attention
            </p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                @if ($anyOverdue)
                    A scheduled task is overdue — the live cron may not be running. Re-add or repair the cPanel cron:
                    <code class="rounded bg-red-100 px-1 dark:bg-red-900/40">* * * * * php /home/USER/naarasim/artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
                @endif
                @if ($queueIsSync) The queue is running synchronously in production. @endif
            </p>
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/30">
            <p class="flex items-center gap-2 font-semibold text-green-700 dark:text-green-300">
                <x-icon name="check" class="h-5 w-5" /> Scheduler + queue look healthy
            </p>
        </div>
    @endif

    {{-- Queue --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Queue connection</p>
            <p @class([
                'mt-1 text-lg font-bold',
                'text-red-600 dark:text-red-400' => $queueIsSync,
                'text-slate-900 dark:text-slate-100' => ! $queueIsSync,
            ])>{{ $queueConnection }}</p>
            @if ($queueIsSync)
                <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">Should be database or redis in production.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Queue backlog</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ is_null($queueBacklog) ? '—' : number_format($queueBacklog) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">{{ is_null($queueBacklog) ? 'Not a database queue' : 'pending jobs' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Oldest job</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ is_null($oldestJobAge) ? '—' : \Illuminate\Support\Carbon::now()->subSeconds($oldestJobAge)->diffForHumans(null, true) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">{{ is_null($oldestJobAge) ? 'queue empty' : 'waiting to drain' }}</p>
        </div>
    </div>

    {{-- Scheduled tasks --}}
    <div class="rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="border-b border-slate-100 p-4 dark:border-[#243352]">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Scheduled tasks</h2>
            <p class="text-xs text-slate-400">Last run is recorded when each task completes. "Never" or "overdue" means the cron isn't firing.</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-[#243352]">
            @foreach ($tasks as $task)
                <div class="flex items-center justify-between gap-3 p-4" wire:key="task-{{ $task['name'] }}">
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $task['label'] }}</p>
                        <p class="font-mono text-[11px] text-slate-400">{{ $task['name'] }} · every {{ \Illuminate\Support\Carbon::now()->subSeconds($task['expected'])->diffForHumans(null, true) }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-right">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $task['ago'] ?? 'never run' }}</p>
                            @if ($task['last_run'])
                                <p class="text-[11px] text-slate-400">{{ $task['last_run'] }}</p>
                            @endif
                        </div>
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $task['overdue'],
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => ! $task['overdue'],
                        ])>{{ $task['overdue'] ? 'Overdue' : 'OK' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

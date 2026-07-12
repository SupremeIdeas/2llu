<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Records a high-severity operational alert (blueprint money-safety: failed
 * money actions "refund + alert"). Writes an error_logs row and mirrors it to
 * the log channel. Richer channels (email/Slack/push) are wired in later
 * hardening modules; this is the durable alert sink they build on.
 */
class AlertAdminJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
        public string $severity = 'critical',
    ) {
    }

    public function handle(): void
    {
        ErrorLog::create([
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
            'severity' => $this->severity,
        ]);

        Log::critical("[$this->code] $this->message", $this->context);
    }
}

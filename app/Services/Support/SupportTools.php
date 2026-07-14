<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\User;
use App\Support\Niche\DataEstimator;
use App\Support\Niche\DeviceCompat;
use App\Support\Niche\LpaActivation;
use App\Support\SupportGuard;

/**
 * The tools the NaaraCare agent may call (Module 24). EVERY tool is bound to one
 * user ($this->user) and reads ONLY that user's records — the agent has no way
 * to name another user or widen the query. Results are additionally run through
 * SupportGuard::scrub() so no cost/profit/secret can ever reach the model. This
 * is how the agent "sees the user's actual situation" (failed order, unsupported
 * device, low balance) without ever touching business economics or other people.
 */
class SupportTools
{
    public function __construct(
        private User $user,
        private ?SupportConversation $conversation = null,
    ) {}

    /** Known in-app destinations the agent can deep-link a user to. */
    private const PAGES = [
        'dashboard' => '/dashboard',
        'catalogue' => '/catalogue',
        'esims' => '/catalogue',
        'wallet' => '/wallet',
        'numbers' => '/numbers',
        'account' => '/account',
        'security' => '/account/security',
        'estimator' => '/data-estimator',
        'refund-policy' => '/refund-policy',
        'faq' => '/faq',
    ];

    /** @return array<int, array<string, mixed>> Anthropic tool schemas. */
    public function schemas(): array
    {
        return [
            [
                'name' => 'check_device_compatibility',
                'description' => "Check whether a phone or device model supports eSIM. Use this whenever the user asks if their device works, or before recommending an eSIM. Returns supported/unsupported/unknown.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['device' => ['type' => 'string', 'description' => 'The device model, e.g. "iPhone 14 Pro" or "Samsung Galaxy S23".']],
                    'required' => ['device'],
                ],
            ],
            [
                'name' => 'get_my_orders',
                'description' => "Get THIS user's recent eSIM and number orders with their status, so you can diagnose their specific problem (e.g. an order stuck pending, expired, or out of data).",
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_my_esim_setup',
                'description' => "Get the eSIM installation details (QR, LPA activation string, step-by-step manual install) for one of THIS user's eSIM orders. Use when they need help installing or activating.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['order_id' => ['type' => 'integer', 'description' => 'The eSIM order id from get_my_orders.']],
                    'required' => ['order_id'],
                ],
            ],
            [
                'name' => 'get_my_wallet_balance',
                'description' => "Get THIS user's current wallet balance. Use when a purchase failed or they ask about funds.",
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_my_numbers',
                'description' => "List THIS user's virtual/verification phone numbers and their status.",
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'estimate_data',
                'description' => 'Estimate how much data a trip needs. Use to help a user pick the right eSIM size.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'profile' => ['type' => 'string', 'enum' => ['light', 'medium', 'heavy']],
                        'days' => ['type' => 'integer'],
                    ],
                    'required' => ['profile', 'days'],
                ],
            ],
            [
                'name' => 'suggest_navigation',
                'description' => 'Give the user a button/link to the right page in the app. Returns a URL the interface will show as a shortcut.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['page' => ['type' => 'string', 'enum' => array_keys(self::PAGES)]],
                    'required' => ['page'],
                ],
            ],
            [
                'name' => 'escalate_to_human',
                'description' => "Hand this conversation to a human staff member. Use ONLY when the problem needs a manual action you cannot do (refund, provider outage, account change) or the user asks for a person.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['reason' => ['type' => 'string', 'description' => 'A short summary of the issue for the staff member.']],
                    'required' => ['reason'],
                ],
            ],
        ];
    }

    /**
     * Execute a tool by name. Always returns an array (scrubbed). Unknown tools
     * and bad input fail soft so the agent can recover.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(string $name, array $input): array
    {
        $result = match ($name) {
            'check_device_compatibility' => $this->checkDevice((string) ($input['device'] ?? '')),
            'get_my_orders' => $this->myOrders(),
            'get_my_esim_setup' => $this->esimSetup((int) ($input['order_id'] ?? 0)),
            'get_my_wallet_balance' => $this->walletBalance(),
            'get_my_numbers' => $this->myNumbers(),
            'estimate_data' => $this->estimate((string) ($input['profile'] ?? 'medium'), (int) ($input['days'] ?? 7)),
            'suggest_navigation' => $this->navigation((string) ($input['page'] ?? '')),
            'escalate_to_human' => $this->escalate((string) ($input['reason'] ?? '')),
            default => ['error' => 'unknown_tool'],
        };

        return SupportGuard::scrub($result);
    }

    // -- Individual tools (all scoped to $this->user) ----------------------

    private function checkDevice(string $device): array
    {
        $result = DeviceCompat::check($device);

        return [
            'device' => $device,
            'status' => $result === true ? 'supported' : ($result === false ? 'unsupported' : 'unknown'),
            'note' => $result === null
                ? "We don't have this exact model on file — most phones from 2019 onward with eSIM support work. Advise the user to check Settings for an 'Add eSIM' option."
                : ($result ? 'This device supports eSIM.' : 'This device does not support eSIM — recommend a physical alternative or a supported device.'),
        ];
    }

    private function myOrders(): array
    {
        $esims = $this->user->esimOrders()->latest()->limit(10)->get()->map(fn ($o) => [
            'id' => $o->id,
            'type' => 'esim',
            'plan' => optional($o->plan)->name,
            'status' => $o->status,
            'activated_at' => optional($o->activated_at)?->toDateString(),
            'expires_at' => optional($o->expires_at)?->toDateString(),
            'data_remaining_mb' => $o->data_remaining_mb,
        ])->all();

        $sms = $this->user->smsOrders()->latest()->limit(10)->get()->map(fn ($o) => [
            'id' => $o->id,
            'type' => 'number',
            'service' => $o->service_name,
            'status' => $o->status,
            'phone_number' => $o->phone_number,
            'ordered_at' => optional($o->ordered_at)?->toDateString(),
        ])->all();

        return ['esim_orders' => $esims, 'number_orders' => $sms];
    }

    private function esimSetup(int $orderId): array
    {
        $order = $this->user->esimOrders()->whereKey($orderId)->first();

        if (! $order) {
            return ['error' => 'not_found', 'note' => 'No eSIM order with that id belongs to this user.'];
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'iccid' => $order->iccid,
            'qr_code_url' => $order->qr_code_url,
            'lpa_string' => $order->lpa_string,
            'manual_steps' => LpaActivation::steps(),
        ];
    }

    private function walletBalance(): array
    {
        $wallet = $this->user->wallet;

        return [
            'usd_balance' => $wallet ? (string) $wallet->usd_balance : '0',
            'ngn_balance' => $wallet ? (string) $wallet->ngn_balance : '0',
        ];
    }

    private function myNumbers(): array
    {
        return [
            'numbers' => $this->user->virtualNumbers()->latest()->limit(10)->get()->map(fn ($n) => [
                'id' => $n->id,
                'phone_number' => $n->phone_number,
                'status' => $n->status,
                'expires_at' => optional($n->expires_at)?->toDateString(),
            ])->all(),
        ];
    }

    private function estimate(string $profile, int $days): array
    {
        $days = max(1, min($days, 365));
        $e = DataEstimator::estimate($profile, $days);

        return ['profile' => $profile, 'days' => $days, 'estimated_gb' => $e['gb'] ?? null, 'estimated_mb' => $e['mb'] ?? null];
    }

    private function navigation(string $page): array
    {
        $path = self::PAGES[$page] ?? null;

        return $path
            ? ['page' => $page, 'url' => $path, 'shown_to_user' => true]
            : ['error' => 'unknown_page'];
    }

    private function escalate(string $reason): array
    {
        if ($this->conversation) {
            $this->conversation->forceFill([
                'escalated' => true,
                'escalation_reason' => mb_substr($reason, 0, 250),
                'escalated_at' => now(),
            ])->save();
        }

        return ['escalated' => true, 'note' => 'A human support agent has been notified and will follow up.'];
    }
}

<?php

namespace App\Livewire;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\NaaraCareAgent;
use App\Support\SupportSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NaaraCare chat (Module 24). A signed-in customer chats with the AI agent,
 * which sees only their own data. Rate-limited. The agent call is synchronous
 * (interactive chat, not a money path) and fails soft if the model is down or
 * unconfigured.
 */
#[Layout('components.layouts.customer')]
class SupportChat extends Component
{
    public ?SupportConversation $conversation = null;

    public string $draft = '';

    /** @var array<int, array{role: string, body: string, nav: ?string}> */
    public array $messages = [];

    public bool $thinking = false;

    public function mount(): void
    {
        $this->conversation = SupportConversation::firstOrCreate(
            ['user_id' => Auth::id(), 'escalated' => false],
            ['title' => 'Support chat'],
        );
        $this->loadMessages();
    }

    private function loadMessages(): void
    {
        $this->messages = $this->conversation->messages()
            ->orderBy('id')->get()
            ->map(fn (SupportMessage $m) => [
                'role' => $m->role,
                'body' => $m->body,
                'nav' => $m->meta['nav'] ?? null,
            ])->all();
    }

    public function send(NaaraCareAgent $agent): void
    {
        $text = trim($this->draft);
        if ($text === '') {
            return;
        }

        // Rate-limit: 20 messages/min per user.
        $key = 'support-chat:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('draft', 'You are sending messages very fast — please wait a moment.');

            return;
        }
        RateLimiter::hit($key, 60);

        // Persist the user's message and echo it immediately.
        $this->conversation->messages()->create(['role' => 'user', 'body' => $text]);
        $this->draft = '';
        $this->loadMessages();

        if (! $agent->available()) {
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => 'Our AI assistant is not available right now. You can reach us on WhatsApp or by email from the Help menu, and a human will get back to you.',
            ]);
            $this->loadMessages();

            return;
        }

        try {
            $result = $agent->respond(Auth::user(), $this->conversation, $text);
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => $result['reply'],
                'meta' => $result['nav'] ? ['nav' => $result['nav']] : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => "Sorry — I hit a snag answering that. If it's urgent, reach us on WhatsApp or email from the Help menu and a human will help.",
            ]);
        }

        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.support-chat', [
            'agentName' => SupportSettings::name(),
        ]);
    }
}

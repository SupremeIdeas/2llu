<?php

namespace App\Livewire;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Services\Support\NaaraCareAgent;
use App\Services\Support\SupportReply;
use App\Support\MediaStorage;
use App\Support\SupportSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * NaaraCare chat (Modules 24–25). A signed-in customer chats with the AI agent
 * (which sees only their own data) or, once escalated, with a human staff member
 * in the same thread. Paying customers also receive spoken (ElevenLabs) replies,
 * and can send voice notes. Rate-limited; fails soft.
 */
#[Layout('components.layouts.customer')]
class SupportChat extends Component
{
    use WithFileUploads;

    public ?SupportConversation $conversation = null;

    public string $draft = '';

    public $voiceNote = null; // uploaded audio

    /** @var array<int, array<string, mixed>> */
    public array $messages = [];

    public function mount(): void
    {
        $this->conversation = SupportConversation::firstOrCreate(
            ['user_id' => Auth::id(), 'status' => 'open'],
            ['title' => 'Support chat'],
        );
        $this->loadMessages();
    }

    private function loadMessages(): void
    {
        $this->messages = $this->conversation->messages()
            ->orderBy('id')->get()
            ->map(fn (SupportMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'nav' => $m->meta['nav'] ?? null,
                'voice' => $m->voice_status === 'ready' ? route('support.voice', $m->id) : null,
                'voice_pending' => $m->voice_status === 'pending',
            ])->all();
    }

    /** Whether a human has taken over this thread (AI stops auto-replying). */
    private function handledByHuman(): bool
    {
        return ! is_null($this->conversation->assigned_to)
            || in_array($this->conversation->status, ['assigned'], true);
    }

    public function send(NaaraCareAgent $agent): void
    {
        $text = trim($this->draft);
        if ($text === '') {
            return;
        }
        if (! $this->throttleOk()) {
            return;
        }

        $this->conversation->messages()->create(['role' => 'user', 'body' => $text]);
        $this->draft = '';
        $this->loadMessages();

        $this->respondTo($agent, $text);
    }

    /**
     * Send a recorded/uploaded voice note. Stored privately; transcribed
     * best-effort so the AI can read it; the audio stays attached for staff.
     */
    public function sendVoice(NaaraCareAgent $agent, VoiceSynthesizer $voice): void
    {
        $this->validate([
            'voiceNote' => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/webm,audio/ogg,audio/mp4,audio/x-m4a', 'max:10240'],
        ]);
        if (! $this->throttleOk()) {
            return;
        }

        $bytes = file_get_contents($this->voiceNote->getRealPath());
        $mime = $this->voiceNote->getMimeType();
        $path = 'support-voice/'.$this->conversation->id.'/'.Str::uuid()->toString().'.'.$this->voiceNote->extension();
        Storage::disk(MediaStorage::privateDisk())->put($path, $bytes);

        $transcript = $voice->transcribe($bytes, $mime);

        $message = $this->conversation->messages()->create([
            'role' => 'user',
            'body' => $transcript ?: '[Voice note]',
            'voice_path' => $path,
            'voice_status' => 'ready',
        ]);

        $this->voiceNote = null;
        $this->loadMessages();

        // Only let the AI answer if we could read the note and no human owns it.
        if ($transcript) {
            $this->respondTo($agent, $transcript);
        }
    }

    private function respondTo(NaaraCareAgent $agent, string $text): void
    {
        if ($this->handledByHuman()) {
            return; // a human will reply
        }

        if (! $agent->available()) {
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => 'Our AI assistant is not available right now. You can reach us on WhatsApp or by email from the Help menu, and a human will get back to you.',
            ]);
            $this->loadMessages();

            return;
        }

        try {
            $result = $agent->respond(Auth::user(), $this->conversation->fresh(), $text);
            app(SupportReply::class)->deliver(
                $this->conversation->fresh(),
                'assistant',
                $result['reply'],
                $result['nav'] ? ['nav' => $result['nav']] : null,
            );
        } catch (\Throwable $e) {
            report($e);
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'body' => "Sorry — I hit a snag answering that. If it's urgent, reach us on WhatsApp or email from the Help menu and a human will help.",
            ]);
        }

        $this->loadMessages();
    }

    private function throttleOk(): bool
    {
        $key = 'support-chat:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('draft', 'You are sending messages very fast — please wait a moment.');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    public function render()
    {
        return view('livewire.support-chat', [
            'agentName' => SupportSettings::name(),
            'humanHandling' => $this->handledByHuman(),
        ]);
    }
}

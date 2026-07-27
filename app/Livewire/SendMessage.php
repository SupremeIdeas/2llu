<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Models\VirtualNumber;
use App\Services\SMS\MessageSenderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Send an SMS from the user's Naara Line (Numbers V6 §6). A modal host that any
 * page can open by dispatching `open-send-message` with { to, name }. Gated on
 * owning an active, SMS-capable Line — Livewire owns the MONEY (live retail
 * quote + atomic charge via MessageSenderService); the provider cost is never
 * exposed. If the user has no Line the modal offers to get one instead.
 */
class SendMessage extends Component
{
    public bool $open = false;

    public string $to = '';

    public string $peerName = '';

    public string $body = '';

    /** The Line to send FROM (defaults to the user's first active SMS line). */
    public ?int $lineId = null;

    public ?string $error = null;

    /** Opened from a contact / dialer row. */
    #[On('open-send-message')]
    public function openFor(string $to = '', string $name = ''): void
    {
        $this->reset('body', 'error');
        $this->to = $to;
        $this->peerName = $name;

        $lines = $this->lines();
        $this->lineId = $lines->first()?->id;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /** Active, SMS-capable Naara Lines the user can send from. */
    protected function lines()
    {
        return VirtualNumber::where('user_id', Auth::id())
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->filter(function (VirtualNumber $n) {
                $caps = (array) $n->capabilities;

                return ! array_key_exists('sms', $caps) || $caps['sms'];
            })
            ->values();
    }

    public function send(MessageSenderService $sender): void
    {
        $this->error = null;

        $line = VirtualNumber::where('user_id', Auth::id())->find($this->lineId);
        if ($line === null) {
            $this->error = 'Choose one of your numbers to send from.';

            return;
        }

        try {
            $sender->send(Auth::user(), $line, trim($this->to), trim($this->body));
        } catch (InsufficientBalanceException $e) {
            $this->error = null;
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Not enough balance',
                message: 'You were not charged. Top up your wallet to send the message.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        } catch (SmsException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('body', 'error');
        $this->open = false;
        $this->dispatch('nx-toast', type: 'success', message: 'Message sent.');
    }

    public function render()
    {
        $lines = $this->lines();

        // Best-effort live quote for the composer (retail only — cost is never
        // surfaced). Only computed while the modal is open and a Line is chosen.
        $quote = null;
        if ($this->open && $this->lineId) {
            $line = $lines->firstWhere('id', $this->lineId);
            if ($line) {
                $quote = app(MessageSenderService::class)->quote($line, $this->body);
            }
        }

        return view('livewire.send-message', [
            'lines' => $lines,
            'quote' => $quote,
        ]);
    }
}

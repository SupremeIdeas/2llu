<?php

namespace App\Services\SMS;

use Illuminate\Http\Request;

/**
 * Voice capability for permanent-number providers (Live Voice — Part A; Twilio
 * primary per CLAUDE.md). Kept separate from NumberProviderInterface so
 * forwarding/calling doesn't bloat the SMS/number contract. Bound on the same
 * providers as the permanent-number lane (voice rides the same credentials).
 */
interface VoiceProviderInterface
{
    public function available(): bool;

    /** Point a provisioned number's inbound Voice URL at our TwiML webhook. */
    public function attachVoiceWebhook(string $numberSid, string $voiceUrl): void;

    /** Remove our Voice URL from a number (stops forwarding at the provider). */
    public function detachVoiceWebhook(string $numberSid): void;

    /**
     * Verify a provider voice webhook is genuine (money-safety rule 9) before
     * trusting it — Twilio signs the exact URL + params (HMAC-SHA1).
     */
    public function verifyWebhook(Request $request, string $url): bool;

    /**
     * Build the TwiML that dials the forward target, preserving the original
     * caller ID and optionally chaining to a fallback on no-answer/busy.
     */
    public function forwardTwiml(string $to, ?string $callerId = null, ?string $fallback = null): string;

    /**
     * Initiate an outbound call bridge between two numbers (used by the dialer /
     * click-to-call). Returns the provider call ref.
     */
    public function bridgeCall(string $from, string $to, string $twimlUrl): string;
}

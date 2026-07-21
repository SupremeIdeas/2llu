<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\NumberProviderInterface;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Twilio — PRIMARY for permanent numbers, voice, and real 2-way SMS
 * (blueprint Section 10.3), billed monthly (Part 14.4). Implements the documented
 * REST API (api.twilio.com/2010-04-01 + pricing.twilio.com/v1) with Basic auth
 * (AccountSid:AuthToken). Endpoints are the official, stable ones — verify in the
 * Twilio sandbox at go-live (rule 1.1). When no key is set every method degrades
 * safely (empty search / OutOfStock) so the platform runs fine without it.
 */
class TwilioService implements NumberProviderInterface, VoiceProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.twilio.account_sid'))
            && ! empty(config('services.twilio.auth_token'));
    }

    // ── Voice (Live Voice — Part A) ─────────────────────────────────────────

    public function available(): bool
    {
        return $this->configured();
    }

    public function attachVoiceWebhook(string $numberSid, string $voiceUrl): void
    {
        if (! $this->configured()) {
            return;
        }
        $this->client()->asForm()
            ->post('/IncomingPhoneNumbers/'.$numberSid.'.json', ['VoiceUrl' => $voiceUrl, 'VoiceMethod' => 'POST'])
            ->throw();
    }

    public function detachVoiceWebhook(string $numberSid): void
    {
        if (! $this->configured()) {
            return;
        }
        $this->client()->asForm()
            ->post('/IncomingPhoneNumbers/'.$numberSid.'.json', ['VoiceUrl' => ''])
            ->throw();
    }

    /**
     * Twilio signs each request: X-Twilio-Signature =
     * base64(HMAC-SHA1(URL + sorted POST key/values, AuthToken)). Constant-time.
     */
    public function verifyWebhook(Request $request, string $url): bool
    {
        $signature = $request->header('X-Twilio-Signature');
        if (! is_string($signature) || ! $this->configured()) {
            return false;
        }

        $params = $request->post();
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, (string) config('services.twilio.auth_token'), true));

        return hash_equals($expected, $signature);
    }

    public function forwardTwiml(string $to, ?string $callerId = null, ?string $fallback = null): string
    {
        $caller = $callerId ? ' callerId="'.htmlspecialchars($callerId, ENT_QUOTES).'"' : '';
        $dial = '<Dial'.$caller.' timeout="20"><Number>'.htmlspecialchars($to, ENT_QUOTES).'</Number></Dial>';
        if ($fallback) {
            $dial .= '<Dial'.$caller.'><Number>'.htmlspecialchars($fallback, ENT_QUOTES).'</Number></Dial>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$dial.'</Response>';
    }

    public function bridgeCall(string $from, string $to, string $twimlUrl): string
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }

        return (string) $this->client()->asForm()
            ->post('/Calls.json', ['To' => $to, 'From' => $from, 'Url' => $twimlUrl])
            ->throw()->json('sid');
    }

    private function client()
    {
        return Http::withBasicAuth(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        )->baseUrl('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid'))
            ->timeout(30);
    }

    /**
     * Search available local numbers. $options: contains (pattern, Twilio meta
     * chars * % + $), sms (bool), voice (bool), limit. Returns masked rows:
     * [['number' => '+1…', 'locality' => '…']].
     */
    public function searchNumbers(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            return [];
        }

        $query = array_filter([
            'Contains' => $options['contains'] ?? null,
            'SmsEnabled' => ($options['sms'] ?? true) ? 'true' : null,
            'VoiceEnabled' => ($options['voice'] ?? true) ? 'true' : null,
            'PageSize' => (int) ($options['limit'] ?? 10),
        ], fn ($v) => $v !== null);

        $res = $this->client()->get('/AvailablePhoneNumbers/'.strtoupper($this->iso($country)).'/Local.json', $query);
        if ($res->failed()) {
            return [];
        }

        return collect($res->json('available_phone_numbers', []))
            ->map(fn ($n) => [
                'number' => (string) ($n['phone_number'] ?? ''),
                'locality' => (string) ($n['locality'] ?? ($n['region'] ?? '')),
            ])
            ->filter(fn ($n) => $n['number'] !== '')
            ->values()
            ->all();
    }

    /**
     * Provision a specific number. $options must carry `number` (from a prior
     * search). Returns the provider ref (SID), the number, and its monthly cost.
     *
     * @return array{provider_ref: string, number: string, monthly_cost: float, capabilities: array}
     */
    public function buyNumber(string $country, array $options = []): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }
        $number = $options['number'] ?? null;
        if (! $number) {
            throw new OutOfStockException('No number selected to provision.');
        }

        $res = $this->client()->asForm()->post('/IncomingPhoneNumbers.json', ['PhoneNumber' => $number]);
        if ($res->failed()) {
            throw new OutOfStockException('Twilio could not provision '.$number.'.');
        }

        return [
            'provider_ref' => (string) $res->json('sid'),
            'number' => (string) ($res->json('phone_number') ?: $number),
            'monthly_cost' => $this->monthlyCost($country),
            'capabilities' => (array) $res->json('capabilities', ['sms' => true, 'voice' => true]),
        ];
    }

    public function sendSms(string $from, string $to, string $body): array
    {
        if (! $this->configured()) {
            throw new OutOfStockException('Twilio is not configured.');
        }
        $res = $this->client()->asForm()->post('/Messages.json', [
            'From' => $from, 'To' => $to, 'Body' => $body,
        ]);
        if ($res->failed()) {
            throw new OutOfStockException('Twilio message send failed.');
        }

        return ['provider_ref' => (string) $res->json('sid'), 'status' => (string) $res->json('status')];
    }

    /** Release the number (stops monthly billing). Best-effort — never throws. */
    public function releaseNumber(string $providerRef): void
    {
        if (! $this->configured() || $providerRef === '') {
            return;
        }
        try {
            $this->client()->delete('/IncomingPhoneNumbers/'.$providerRef.'.json');
        } catch (\Throwable) {
            // best-effort; the renewal job stops charging regardless of release success
        }
    }

    /** Live monthly wholesale (USD) via the Twilio Pricing API, config fallback. */
    public function monthlyCost(string $country): float
    {
        $fallback = (float) config('services.twilio.default_monthly_cost', 1.15);
        if (! $this->configured()) {
            return $fallback;
        }
        try {
            $res = Http::withBasicAuth(
                (string) config('services.twilio.account_sid'),
                (string) config('services.twilio.auth_token'),
            )->timeout(20)->get('https://pricing.twilio.com/v1/PhoneNumbers/Countries/'.strtoupper($this->iso($country)));

            $local = collect($res->json('phone_number_prices', []))
                ->firstWhere('number_type', 'local');
            $price = $local['current_price'] ?? null;

            return $price !== null ? (float) $price : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** Map a loose country name/code to an ISO-3166 alpha-2 (Twilio expects it). */
    private function iso(string $country): string
    {
        $c = strtolower(trim($country));
        $map = ['usa' => 'US', 'us' => 'US', 'united states' => 'US', 'uk' => 'GB',
            'united kingdom' => 'GB', 'england' => 'GB', 'nigeria' => 'NG', 'ghana' => 'GH',
            'kenya' => 'KE', 'south africa' => 'ZA', 'canada' => 'CA'];

        return $map[$c] ?? strtoupper(substr($country, 0, 2));
    }
}

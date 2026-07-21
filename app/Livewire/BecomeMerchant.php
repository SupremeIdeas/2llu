<?php

namespace App\Livewire;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Services\Kyc\KycService;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantService;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer path to becoming a merchant (ROADMAP §Layer 3.1). Two stages, shown
 * one at a time: (1) business (KYB / KYC L3) verification, then (2) the merchant
 * application. Admin approval activates the storefront and grants the role.
 */
#[Layout('components.layouts.customer')]
class BecomeMerchant extends Component
{
    // KYB form.
    public string $country = 'NG';

    public string $regType = 'CAC';

    public string $regNumber = '';

    // Application form.
    public string $businessName = '';

    public string $brandColor = '#0A6E6E';

    public ?string $error = null;

    public function submitKyb(KycService $kyc): void
    {
        $this->validate([
            'country' => 'required|string|size:2',
            'regType' => 'required|string|max:40',
            'regNumber' => 'required|string|max:64',
        ]);

        $kyc->submit(Auth::user(), KycVerification::L3, [
            'country' => strtoupper($this->country),
            'id_type' => $this->regType,
            'id_number' => $this->regNumber,
        ]);

        $this->reset('regNumber');
        $this->dispatch('nx-toast', type: 'success', message: 'Business details submitted for verification.');
    }

    public function payEnrollment(MerchantService $merchants): void
    {
        $this->error = null;
        try {
            $merchants->payEnrollment(Auth::user());
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Fast-route enrollment paid — you can apply now.');
    }

    public function apply(MerchantService $merchants): void
    {
        $this->error = null;
        $this->validate([
            'businessName' => 'required|string|min:2|max:80',
            'brandColor' => 'nullable|string|max:9',
        ]);

        try {
            $merchants->apply(Auth::user(), [
                'business_name' => $this->businessName,
                'brand_color' => $this->brandColor,
            ]);
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Application submitted — we’ll review it shortly.');
    }

    public function render(KycService $kyc, MerchantService $merchants)
    {
        $user = Auth::user();
        $kybVerified = $kyc->hasLevel($user, KycVerification::L3);

        return view('livewire.become-merchant', [
            'programmeOpen' => MerchantSettings::enabled(),
            'kybVerified' => $kybVerified,
            'kybAttempt' => $kyc->latest($user, KycVerification::L3),
            'eligibility' => $kybVerified ? $merchants->eligibility($user) : null,
            'merchant' => Merchant::where('owner_user_id', $user->id)->latest('id')->first(),
        ]);
    }
}

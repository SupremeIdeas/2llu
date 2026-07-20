<?php

namespace App\Livewire;

use App\Models\PayoutAccount;
use App\Services\Credits\CreditService;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\WithdrawalService;
use App\Support\CreditSettings;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer cash-out (ROADMAP §Layer 1). Manage bank/payout accounts (name
 * resolved before saving) and withdraw withdrawable NaaraCredits to one. The
 * route is KYC-L2 gated (kyc:2 middleware), so only verified users reach it.
 */
#[Layout('components.layouts.customer')]
class Withdraw extends Component
{
    // Add-account form.
    public string $country = 'NG';

    public string $bankCode = '';

    public string $accountNumber = '';

    /** @var list<array{code: string, name: string}> */
    public array $banks = [];

    public ?string $accountError = null;

    // Withdraw form.
    public ?int $accountId = null;

    public $amountUsd = '';

    public ?string $withdrawError = null;

    public function mount(): void
    {
        $this->loadBanks();
        $this->accountId = PayoutAccount::where('user_id', Auth::id())->where('is_default', true)->value('id');
    }

    public function updatedCountry(): void
    {
        $this->bankCode = '';
        $this->loadBanks();
    }

    private function loadBanks(): void
    {
        $this->banks = app(PayoutAccountService::class)->banksFor(strtoupper($this->country))['banks'];
    }

    public function addAccount(PayoutAccountService $accounts): void
    {
        $this->accountError = null;
        $this->validate([
            'country' => 'required|string|size:2',
            'bankCode' => 'required|string',
            'accountNumber' => 'required|string|max:32',
        ]);

        try {
            $account = $accounts->addAccount(Auth::user(), [
                'country' => $this->country,
                'currency' => $this->currencyFor($this->country),
                'bank_code' => $this->bankCode,
                'bank_name' => collect($this->banks)->firstWhere('code', $this->bankCode)['name'] ?? null,
                'account_number' => $this->accountNumber,
            ]);
        } catch (AccountResolutionException $e) {
            $this->accountError = $e->getMessage();

            return;
        }

        $this->reset('accountNumber');
        $this->accountId = $account->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Account verified as '.$account->account_name.'.');
    }

    public function setDefault(int $id, PayoutAccountService $accounts): void
    {
        $accounts->setDefault(Auth::user(), PayoutAccount::findOrFail($id));
        $this->accountId = $id;
    }

    public function removeAccount(int $id, PayoutAccountService $accounts): void
    {
        $accounts->remove(Auth::user(), PayoutAccount::findOrFail($id));
        if ($this->accountId === $id) {
            $this->accountId = null;
        }
    }

    public function withdraw(WithdrawalService $withdrawals): void
    {
        $this->withdrawError = null;
        $this->validate([
            'accountId' => 'required|integer',
            'amountUsd' => 'required|numeric|min:0.01',
        ]);

        $account = PayoutAccount::where('user_id', Auth::id())->find($this->accountId);
        if ($account === null) {
            $this->withdrawError = 'Choose a payout account.';

            return;
        }

        try {
            $credits = CreditSettings::usdToCredits((float) $this->amountUsd);
            $withdrawals->request(Auth::user(), $credits, $account);
        } catch (PayoutException $e) {
            $this->withdrawError = $e->getMessage();

            return;
        }

        $this->reset('amountUsd');
        $this->dispatch('nx-toast', type: 'success', message: 'Withdrawal requested — we’ll process it shortly.');
    }

    private function currencyFor(string $country): string
    {
        return ['NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR'][strtoupper($country)] ?? 'USD';
    }

    public function render(WithdrawalService $withdrawals, CreditService $credits)
    {
        $user = Auth::user();

        return view('livewire.withdraw', [
            'accounts' => PayoutAccount::where('user_id', $user->id)->latest()->get(),
            'availableUsd' => $withdrawals->availableUsd($user),
            'withdrawableCredits' => $credits->withdrawableBalance($user),
            'minWithdrawal' => PayoutSettings::minWithdrawal(),
            'enabled' => PayoutSettings::enabled(),
        ]);
    }
}

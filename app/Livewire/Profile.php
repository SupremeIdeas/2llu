<?php

namespace App\Livewire;

use App\Services\Pricing\CurrencyService;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Extended profile (owner request). A self-service page where a user can build a
 * solid profile — display name, contact, bio, location, DOB, avatar, and their
 * preferred display currency. Purely a profile: nothing here touches money, auth,
 * or KYC. A completeness meter nudges them to fill it in.
 */
#[Layout('components.layouts.customer')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $phone = '';

    public string $bio = '';

    public string $city = '';

    public string $addressLine = '';

    public string $postalCode = '';

    public string $countryCode = '';

    public string $dateOfBirth = '';

    public string $displayCurrency = 'USD';

    public $avatar = null; // new upload

    public function mount(): void
    {
        $u = Auth::user();
        $this->name = (string) $u->name;
        $this->phone = (string) $u->phone;
        $this->bio = (string) $u->bio;
        $this->city = (string) $u->city;
        $this->addressLine = (string) $u->address_line;
        $this->postalCode = (string) $u->postal_code;
        $this->countryCode = (string) $u->country_code;
        $this->dateOfBirth = $u->date_of_birth?->format('Y-m-d') ?? '';
        $this->displayCurrency = \App\Support\LocaleCurrency::resolve($u);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'bio' => ['nullable', 'string', 'max:400'],
            'city' => ['nullable', 'string', 'max:80'],
            'addressLine' => ['nullable', 'string', 'max:160'],
            'postalCode' => ['nullable', 'string', 'max:32'],
            'countryCode' => ['nullable', 'string', 'size:2'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'displayCurrency' => ['required', 'string', 'in:'.implode(',', array_keys(CurrencyService::SUPPORTED))],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $u = Auth::user();

        $data = [
            'name' => trim($this->name),
            'phone' => $this->phone ?: null,
            'bio' => $this->bio ?: null,
            'city' => $this->city ?: null,
            'address_line' => $this->addressLine ?: null,
            'postal_code' => $this->postalCode ?: null,
            'country_code' => $this->countryCode ? strtoupper($this->countryCode) : null,
            'date_of_birth' => $this->dateOfBirth ?: null,
        ];

        if ($this->avatar) {
            $data['avatar'] = MediaStorage::storePublic($this->avatar, 'avatars');
        }

        $u->forceFill($data)->save();
        \App\Support\LocaleCurrency::choose($u, $this->displayCurrency);
        $this->reset('avatar');

        $this->dispatch('nx-toast', type: 'success', message: 'Profile saved.');
    }

    public function render()
    {
        return view('livewire.profile', [
            'user' => Auth::user()->fresh(),
            'currencyOptions' => CurrencyService::SUPPORTED,
        ]);
    }
}

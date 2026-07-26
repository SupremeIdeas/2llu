<?php

namespace Tests\Feature;

use App\Livewire\ServicePicker;
use App\Models\User;
use App\Support\CountryPickerSources;
use App\Support\ServicePickerSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shared ServicePicker + the Numbers country source (Numbers V6 §0/§3):
 * both feed the same reusable modals used by Naara Verify and Naara Rent.
 */
class ServicePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_source_lists_services_sorted_by_name(): void
    {
        $options = ServicePickerSources::options();

        $this->assertNotEmpty($options);
        $names = array_column($options, 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
        $this->assertArrayHasKey('slug', $options[0]);
    }

    public function test_the_service_picker_emits_the_pick_with_its_opener_token(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ServicePicker::class)
            ->call('openModal', 'verify', 'Choose a service')
            ->assertSet('open', true)
            ->call('pick', 'whatsapp', 'WhatsApp')
            ->assertDispatched('service-picked', slug: 'whatsapp', name: 'WhatsApp', for: 'verify')
            ->assertSet('open', false);
    }

    public function test_the_numbers_country_source_carries_dial_codes(): void
    {
        $options = CountryPickerSources::options('numbers');

        $this->assertNotEmpty($options);
        $byName = collect($options)->keyBy('code');

        // Nigeria's slug carries its dial code; every row has a name.
        $ng = collect($options)->firstWhere('code', 'nigeria');
        $this->assertNotNull($ng);
        $this->assertSame('+234', $ng['dial']);
        $this->assertArrayHasKey('name', $options[0]);
    }
}

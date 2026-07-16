<?php

namespace Tests\Feature;

use App\Livewire\Admin\ServiceIconsPage;
use App\Models\User;
use App\Support\CountryFlags;
use App\Support\ServiceIcons;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 27.5 — service logos + country flags for numbers/eSIMs.
 */
class ServiceIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        ServiceIcons::flush();
    }

    public function test_resolution_order_is_override_then_api_then_sprite_then_letter(): void
    {
        // Bundled sprite for a known service.
        $this->assertSame(['type' => 'sprite', 'id' => 'svc-whatsapp'], ServiceIcons::resolve('whatsapp'));

        // Provider API artwork beats the sprite.
        $api = ServiceIcons::resolve('whatsapp', 'https://cdn.provider/wa.png');
        $this->assertSame('img', $api['type']);
        $this->assertSame('https://cdn.provider/wa.png', $api['url']);

        // Admin override beats everything.
        ServiceIcons::saveOverride('whatsapp', 'https://our.cdn/official-wa.png');
        $this->assertSame('https://our.cdn/official-wa.png', ServiceIcons::resolve('whatsapp', 'https://cdn.provider/wa.png')['url']);

        // Unknown service falls back to a letter avatar — never broken.
        $this->assertSame(['type' => 'letter', 'letter' => 'B'], ServiceIcons::resolve('bumble'));

        // Aliases map (twitter -> x sprite).
        $this->assertSame('svc-x', ServiceIcons::resolve('twitter')['id']);
    }

    public function test_country_flags_map_slugs_and_iso_codes(): void
    {
        $this->assertSame('fi fi-ng', CountryFlags::flagClass('nigeria'));
        $this->assertSame('fi fi-us', CountryFlags::flagClass('usa'));
        $this->assertSame('fi fi-gb', CountryFlags::flagClass('england'));
        $this->assertSame('fi fi-us', CountryFlags::flagClass('US')); // eSIM plan ISO codes
        $this->assertNull(CountryFlags::flagClass('atlantis'));
    }

    public function test_admin_can_upload_an_override_and_remove_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ServiceIconsPage::class)
            ->set('uploadSlug', 'okcupid')
            ->set('upload', UploadedFile::fake()->image('okc.png', 128, 128))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertArrayHasKey('okcupid', ServiceIcons::overrides());
        $this->assertSame('img', ServiceIcons::resolve('okcupid')['type']);

        Livewire::actingAs($admin)->test(ServiceIconsPage::class)->call('remove', 'okcupid');
        $this->assertArrayNotHasKey('okcupid', ServiceIcons::overrides());
    }

    public function test_service_icons_page_is_admin_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/service-icons')->assertNotFound();
    }

    public function test_get_number_page_shows_service_logos_and_flags(): void
    {
        $user = User::factory()->create()->fresh();

        $this->actingAs($user)->get('/numbers')
            ->assertOk()
            ->assertSee('svc-whatsapp', false)   // sprite reference rendered
            ->assertSee('fi fi-us', false);      // default country flag (usa)
    }

    public function test_dashboard_renders_the_premium_wallet_card(): void
    {
        $user = User::factory()->create()->fresh();
        \App\Models\UserWallet::create(['user_id' => $user->id, 'usd_balance' => 12.5, 'ngn_balance' => 4000]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Wallet balance')
            ->assertSee('$12.50')
            ->assertSee('eSIM Data Plans'); // value showcase for a fresh account
    }

    public function test_landing_page_carries_the_products_pin_and_countups(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-products-pin', false)
            ->assertSee('What NaaraSim Gives You')
            ->assertSee('data-countup="190"', false);
    }
}

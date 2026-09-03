<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimControlCenter;
use App\Livewire\Catalogue;
use App\Models\EsimCountryImage;
use App\Models\EsimPlan;
use App\Models\User;
use App\Support\CountryNames;
use App\Support\EsimCatalogue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Popular Destinations" photo-card row on the eSIM Popular tab (owner
 * request). One shared layout across every theme. A country appears purely
 * because it has an admin-featured (is_featured) single-country local plan —
 * the same signal the Popular tab's plan list already uses, no new curation
 * surface — and its photo is the pre-existing admin-editable per-country
 * image (EsimCountryImage.detail_image_path, set in Admin → eSIM Control
 * Center) — no new admin uploader either.
 */
class EsimPopularDestinationsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $overrides = []): EsimPlan
    {
        return EsimPlan::create(array_merge([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Test plan',
            'has_voice' => false, 'is_active' => true, 'is_featured' => true,
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => ['FR'],
            'data_mb' => 1024, 'validity_days' => 7,
            'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ], $overrides));
    }

    public function test_a_country_with_a_featured_local_plan_shows_as_a_popular_destination(): void
    {
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('Popular Destinations')
            ->assertSee(CountryNames::name('FR'))
            ->assertSee("openCountry('FR')", false);
    }

    public function test_a_country_with_no_featured_plan_is_excluded(): void
    {
        $this->plan(['is_featured' => false]);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertDontSee('Popular Destinations');
    }

    public function test_a_multi_country_or_global_featured_plan_is_excluded(): void
    {
        // Regional (2+ countries) — the Regions tab covers this, not a
        // single-destination card.
        $this->plan(['coverage_type' => EsimPlan::COVERAGE_REGIONAL, 'countries' => ['FR', 'DE'], 'region_slug' => 'europe']);
        // Global — no single destination to show a card for.
        $this->plan(['coverage_type' => EsimPlan::COVERAGE_GLOBAL, 'countries' => []]);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertDontSee('Popular Destinations');
    }

    public function test_the_photo_is_the_existing_admin_editable_country_image(): void
    {
        EsimCountryImage::create(['country_code' => 'FR', 'detail_image_path' => 'https://cdn.test/fr-photo.webp']);
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSee('https://cdn.test/fr-photo.webp', false);
    }

    public function test_a_country_with_no_photo_still_renders_a_clean_card(): void
    {
        // No EsimCountryImage row at all for FR — photo is optional, never blocking.
        $this->plan();

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertOk()
            ->assertSee(CountryNames::name('FR'));
    }

    public function test_the_teaser_shows_the_cheapest_featured_plans_price_and_data(): void
    {
        $this->plan(['computed_retail_usd' => 9, 'data_mb' => 1024]);
        $this->plan(['provider_plan_id' => 'p-'.uniqid(), 'computed_retail_usd' => 4, 'data_mb' => 512]);

        $rows = EsimCatalogue::popularDestinations(false);
        $fr = collect($rows)->firstWhere('code', 'FR');

        $this->assertNotNull($fr);
        $this->assertSame(4.0, $fr['from_usd']); // the cheaper of the two
        $this->assertSame(512, $fr['data_mb']);
        $this->assertSame(2, $fr['count']);
    }

    public function test_toggling_featured_off_removes_the_destination_without_a_manual_cache_flush(): void
    {
        $this->seed(RoleSeeder::class);
        $plan = $this->plan();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->assertSee('Popular Destinations');

        Livewire::actingAs($admin)->test(EsimControlCenter::class)->call('togglePopular', $plan->id);

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)->assertDontSee('Popular Destinations');
    }

    public function test_the_full_line_and_data_line_are_scoped_independently(): void
    {
        $this->plan(['has_voice' => false]); // FR on the data-only line

        Livewire::actingAs(User::factory()->create())->test(Catalogue::class)
            ->assertSet('tab', 'data')
            ->assertSee('Popular Destinations')
            ->call('setTab', 'full')
            ->assertDontSee('Popular Destinations');
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProductLines;
use App\Models\User;
use App\Support\ProductLineSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BLUEPRINT-batch1-sections §4 — the product-line CMS + admin repeater.
 */
class ProductLinesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_defaults_are_the_six_named_products_with_connect_and_rent_marked_draft(): void
    {
        $slugs = array_column(ProductLineSettings::products(), 'slug');
        $this->assertSame(
            ['naara-data', 'naara-connect', 'naara-verify', 'naara-rent', 'naara-line', 'naara-gift'],
            $slugs,
        );

        $byslug = collect(ProductLineSettings::products())->keyBy('slug');
        $this->assertTrue($byslug['naara-connect']['is_draft']);
        $this->assertTrue($byslug['naara-rent']['is_draft']);
        $this->assertFalse($byslug['naara-data']['is_draft']);
        // Every product carries the 3-beat arc.
        $this->assertCount(3, $byslug['naara-data']['modal_blocks']);
    }

    public function test_slides_map_products_and_send_guests_to_register(): void
    {
        // Guest: CTA resolves to register (matching the old panel behaviour).
        $slides = ProductLineSettings::slides();
        $this->assertSame('Naara Data', $slides[0]['title']);
        $this->assertStringContainsString('/register', $slides[0]['cta_url']);
        // Slide shape carries the modal payload for the FAB.
        $this->assertNotEmpty($slides[0]['modal_blocks']);
    }

    public function test_homepage_products_section_renders_the_carousel(): void
    {
        $s = ['eyebrow' => 'One Family', 'headline' => 'What Naara Gives You', 'subtext' => 'x'];
        $html = Blade::render(view('marketing.home.products', ['s' => $s])->render());

        $this->assertStringContainsString('storytellingCarousel(', $html);
        $this->assertStringContainsString("key: 'product-lines'", $html);
        $this->assertStringContainsString('Naara Data', $html);
    }

    public function test_admin_page_is_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(ProductLines::class)->assertForbidden();
    }

    public function test_admin_can_edit_reorder_and_save_products(): void
    {
        Livewire::actingAs($this->admin())->test(ProductLines::class)
            ->assertSet('products.0.slug', 'naara-data')
            ->set('products.0.title', 'Naara Data Plus')
            ->call('moveProduct', 0, 1)              // data ↔ connect
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', 'saved');

        $products = ProductLineSettings::products();
        $this->assertSame('naara-connect', $products[0]['slug']); // reordered
        $this->assertSame('Naara Data Plus', collect($products)->firstWhere('slug', 'naara-data')['title']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_lines.updated']);
    }

    public function test_title_is_required(): void
    {
        Livewire::actingAs($this->admin())->test(ProductLines::class)
            ->set('products.0.title', '')
            ->call('save')
            ->assertHasErrors('products.0.title');
    }

    public function test_restore_defaults_reverts_edits(): void
    {
        ProductLineSettings::save([['slug' => 'x', 'title' => 'Only one', 'modal_blocks' => [], 'modal_gallery' => []]]);
        $this->assertCount(1, ProductLineSettings::products());

        Livewire::actingAs($this->admin())->test(ProductLines::class)
            ->call('restoreDefaults')
            ->assertSet('saved', 'restored');

        $this->assertCount(6, ProductLineSettings::products());
    }
}

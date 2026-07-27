<?php

namespace Tests\Feature;

use App\Livewire\Admin\PageBuilder;
use App\Models\PageSection;
use App\Models\PageSectionVersion;
use App\Models\User;
use App\Services\Builder\PageBuilderService;
use App\Support\HtmlSanitizer;
use App\Support\PageSections;
use App\Support\SectionLibrary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Universal Section Builder foundation. Covers the draft→publish→live boundary
 * (drafts never leak to the public until published), version rollback, the
 * admin-only gate, and — critically — that the Custom-HTML type is allowlist
 * sanitized, not raw-rendered.
 */
class PageBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_html_sanitizer_strips_scripts_and_handlers_but_keeps_safe_markup(): void
    {
        $dirty = '<p class="x" onclick="steal()">Hello <strong>world</strong></p>'
            .'<script>alert(1)</script>'
            .'<a href="javascript:alert(1)">bad</a>'
            .'<a href="https://naara.sim" target="_blank">good</a>'
            .'<iframe src="https://evil"></iframe>';

        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringContainsString('class="x"', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        // A surviving _blank link is hardened against reverse-tabnabbing.
        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_draft_edits_stay_invisible_until_published(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('home', 'hero');
        $svc->updateConfig($section, array_merge($section->config, ['headline' => 'Draft only']));

        // Nothing published yet → the public renderer sees nothing.
        $this->assertFalse(PageSections::hasLive('home'));
        $this->assertSame([], PageSections::live('home'));

        $svc->publish('home', $this->admin());

        $live = PageSections::live('home');
        $this->assertCount(1, $live);
        $this->assertSame('Draft only', $live[0]['config']['headline']);
    }

    public function test_publishing_a_new_draft_does_not_change_live_until_republished(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('home', 'hero');
        $svc->updateConfig($section, array_merge($section->config, ['headline' => 'V1']));
        $svc->publish('home', $this->admin());

        // Edit the draft again — live still shows V1.
        $svc->updateConfig($section->fresh(), array_merge($section->config, ['headline' => 'V2 draft']));
        $this->assertSame('V1', PageSections::live('home')[0]['config']['headline']);

        $svc->publish('home', $this->admin());
        $this->assertSame('V2 draft', PageSections::live('home')[0]['config']['headline']);
    }

    public function test_rollback_restores_a_prior_version_and_republishes(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('home', 'hero');
        $svc->updateConfig($section, array_merge($section->config, ['headline' => 'Original']));
        $v1 = $svc->publish('home', $this->admin());

        $svc->updateConfig($section->fresh(), array_merge($section->config, ['headline' => 'Changed']));
        $svc->publish('home', $this->admin());
        $this->assertSame('Changed', PageSections::live('home')[0]['config']['headline']);

        $svc->rollback($v1->fresh(), $this->admin());

        $this->assertSame('Original', PageSections::live('home')[0]['config']['headline']);
        // The draft rows were restored too, so the admin sees what's live.
        $this->assertSame('Original', PageSection::where('page_key', 'home')->first()->config['headline']);
    }

    public function test_reorder_changes_the_section_sequence(): void
    {
        $svc = app(PageBuilderService::class);
        $a = $svc->addSection('home', 'hero');
        $b = $svc->addSection('home', 'two_column');

        $svc->reorder('home', [$b->id, $a->id]);

        $order = PageSection::where('page_key', 'home')->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame([$b->id, $a->id], $order);
    }

    public function test_builder_is_admin_only(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(PageBuilder::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(PageBuilder::class)->assertOk();
    }

    public function test_custom_html_is_sanitized_when_saved_through_the_builder(): void
    {
        $admin = $this->admin();
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('landing', 'custom_html');

        Livewire::actingAs($admin)->test(PageBuilder::class, ['page' => 'landing'])
            ->call('edit', $section->id)
            ->set('config.html', '<p>ok</p><script>alert(1)</script>')
            ->call('saveSection');

        $saved = $section->fresh()->config['html'];
        $this->assertStringContainsString('<p>ok</p>', $saved);
        $this->assertStringNotContainsString('<script', $saved);
    }

    public function test_hero_preset_sets_its_mode_and_scheme(): void
    {
        $config = SectionLibrary::defaultsFor('hero');
        $config = SectionLibrary::applyHeroPreset($config, 'showcase');

        $this->assertSame('showcase', $config['preset']);
        $this->assertSame('images', $config['mode']);
        $this->assertSame('dark', $config['scheme']);
    }

    public function test_publishing_records_version_history(): void
    {
        $svc = app(PageBuilderService::class);
        $svc->addSection('home', 'hero');
        $svc->publish('home', $this->admin());
        $svc->publish('home', $this->admin());

        $this->assertSame(2, PageSectionVersion::where('page_key', 'home')->count());
        $this->assertSame(1, PageSectionVersion::where('page_key', 'home')->where('is_live', true)->count());
    }
}

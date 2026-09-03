<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * HOTFIX §8 — the brand logo sizes from a named `size` prop so it reads
 * consistently everywhere, instead of ad-hoc per-usage height classes.
 */
class BrandLogoSizingTest extends TestCase
{
    public function test_named_sizes_map_to_consistent_dimensions(): void
    {
        // The product (NaaraSim) wordmark is wide (~4:1), so it uses the compact
        // "wide" height map — a stable reference for the named-size contract.
        $md = Blade::render('<x-brand-logo variant="product" size="md" />');
        $this->assertStringContainsString('h-8', $md);
        $this->assertStringContainsString('max-w-[160px]', $md);

        $lg = Blade::render('<x-brand-logo variant="product" size="lg" />');
        $this->assertStringContainsString('h-9', $lg);
        $this->assertStringContainsString('max-w-[180px]', $lg);

        $xl = Blade::render('<x-brand-logo variant="product" size="xl" />');
        $this->assertStringContainsString('md:h-28', $xl);
    }

    public function test_family_and_gift_marks_use_the_taller_map_for_visual_weight(): void
    {
        // The near-square Naara family + Naara Gift marks read smaller than the
        // wide NaaraSim wordmark at the same height, so they get a taller map
        // (owner request: "bold like NaaraSim").
        $family = Blade::render('<x-brand-logo variant="family" size="md" />');
        $this->assertStringContainsString('h-11', $family);
        $this->assertStringContainsString('max-w-[140px]', $family);

        $gift = Blade::render('<x-brand-logo variant="gift" size="lg" />');
        $this->assertStringContainsString('h-12', $gift);
        $this->assertStringContainsString('max-w-[150px]', $gift);
    }

    public function test_it_defaults_to_md_and_class_is_for_spacing_on_the_wrapper(): void
    {
        $html = Blade::render('<x-brand-logo variant="product" class="mb-2" />');
        $this->assertStringContainsString('h-8', $html);   // default md
        $this->assertStringContainsString('mb-2', $html);  // spacing utility kept
    }
}

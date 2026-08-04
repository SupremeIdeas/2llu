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
        $md = Blade::render('<x-brand-logo variant="family" size="md" />');
        $this->assertStringContainsString('h-8', $md);
        $this->assertStringContainsString('max-w-[150px]', $md);

        $lg = Blade::render('<x-brand-logo variant="family" size="lg" />');
        $this->assertStringContainsString('h-9', $lg);
        $this->assertStringContainsString('max-w-[170px]', $lg);

        $xl = Blade::render('<x-brand-logo variant="family" size="xl" />');
        $this->assertStringContainsString('md:h-28', $xl);
    }

    public function test_it_defaults_to_md_and_class_is_for_spacing_on_the_wrapper(): void
    {
        $html = Blade::render('<x-brand-logo variant="family" class="mb-2" />');
        $this->assertStringContainsString('h-8', $html);   // default md
        $this->assertStringContainsString('mb-2', $html);  // spacing utility kept
    }
}

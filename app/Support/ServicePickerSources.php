<?php

namespace App\Support;

/**
 * The single source of service data behind the shared ServicePicker modal
 * (Numbers V6 §0). Returns a normalised, name-sorted list of pickable services
 * so the picker view stays a dumb, reusable component.
 *
 * `[ ['slug' => 'whatsapp', 'name' => 'WhatsApp'], ... ]`
 */
class ServicePickerSources
{
    /**
     * Every OTP/rental service, slug + friendly name, sorted by name.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public static function options(): array
    {
        $rows = [];
        foreach (NumberCatalogue::services() as $slug => $label) {
            $rows[] = ['slug' => $slug, 'name' => $label];
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }
}

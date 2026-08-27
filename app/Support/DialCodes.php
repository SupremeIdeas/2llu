<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 → international dial code, for the in-browser dialer's
 * country picker (Numbers overhaul §3). The dialer used to demand a full
 * `+<code>…` typed by hand; this lets the user pick the country and just key
 * the local number.
 *
 * The list is Africa-weighted (NaaraSim's home markets pinned first via
 * PRIORITY) then the global majors, so the picker opens on the countries our
 * users dial most without scrolling. Flags render through <x-country-flag>
 * (self-hosted SVG — never emoji), and names come from CountryNames so there is
 * no second display-name map to drift.
 */
class DialCodes
{
    /** ISO2 => dial code (digits only, no '+'). */
    private const CODES = [
        // Africa
        'ng' => '234', 'gh' => '233', 'ke' => '254', 'za' => '27', 'et' => '251',
        'tz' => '255', 'ug' => '256', 'rw' => '250', 'cm' => '237', 'ci' => '225',
        'sn' => '221', 'zm' => '260', 'zw' => '263', 'bw' => '267', 'mz' => '258',
        'ao' => '244', 'eg' => '20', 'ma' => '212', 'dz' => '213', 'tn' => '216',
        'ml' => '223', 'bf' => '226', 'bj' => '229', 'ne' => '227', 'tg' => '228',
        'sl' => '232', 'lr' => '231', 'gm' => '220', 'gn' => '224', 'cd' => '243',
        'cg' => '242', 'ga' => '241', 'na' => '264', 'mw' => '265', 'ls' => '266',
        'sz' => '268', 'mg' => '261', 'mu' => '230', 'so' => '252', 'sd' => '249',
        'ss' => '211', 'ly' => '218', 'mr' => '222',
        // North America
        'us' => '1', 'ca' => '1', 'mx' => '52',
        // Europe
        'gb' => '44', 'ie' => '353', 'fr' => '33', 'de' => '49', 'es' => '34',
        'it' => '39', 'pt' => '351', 'nl' => '31', 'be' => '32', 'ch' => '41',
        'at' => '43', 'se' => '46', 'no' => '47', 'dk' => '45', 'fi' => '358',
        'pl' => '48', 'ro' => '40', 'ua' => '380', 'ru' => '7', 'tr' => '90',
        'gr' => '30', 'cz' => '420', 'hu' => '36',
        // Middle East
        'ae' => '971', 'sa' => '966', 'qa' => '974', 'kw' => '965', 'il' => '972',
        'lb' => '961', 'jo' => '962', 'iq' => '964',
        // Asia
        'in' => '91', 'pk' => '92', 'bd' => '880', 'ph' => '63', 'id' => '62',
        'vn' => '84', 'th' => '66', 'my' => '60', 'sg' => '65', 'cn' => '86',
        'jp' => '81', 'kr' => '82', 'hk' => '852', 'tw' => '886', 'lk' => '94',
        'np' => '977',
        // South America
        'br' => '55', 'ar' => '54', 'co' => '57', 'cl' => '56', 'pe' => '51',
        // Oceania
        'au' => '61', 'nz' => '64',
    ];

    /** Home markets pinned to the top of the picker, in this order. */
    private const PRIORITY = ['ng', 'gh', 'ke', 'za', 'us', 'gb'];

    /** Dial code (no '+') for an ISO2 / slug, or null when unknown. */
    public static function codeFor(?string $country): ?string
    {
        $iso = CountryFlags::iso($country);

        return $iso !== null ? (self::CODES[$iso] ?? null) : null;
    }

    /**
     * The picker list: priority markets first, then the rest alphabetically by
     * name. Each row is ['iso' => , 'name' => , 'code' => ] (code has no '+').
     */
    public static function all(): array
    {
        $rows = [];
        foreach (self::CODES as $iso => $code) {
            $rows[$iso] = ['iso' => $iso, 'name' => CountryNames::name($iso), 'code' => $code];
        }

        $priority = [];
        foreach (self::PRIORITY as $iso) {
            if (isset($rows[$iso])) {
                $priority[] = $rows[$iso];
                unset($rows[$iso]);
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values(array_merge($priority, $rows));
    }

    /** The row for an ISO2 / slug, defaulting to Nigeria (NaaraSim's home). */
    public static function default(?string $country): array
    {
        $iso = CountryFlags::iso($country);
        if ($iso !== null && isset(self::CODES[$iso])) {
            return ['iso' => $iso, 'name' => CountryNames::name($iso), 'code' => self::CODES[$iso]];
        }

        return ['iso' => 'ng', 'name' => CountryNames::name('ng'), 'code' => self::CODES['ng']];
    }
}

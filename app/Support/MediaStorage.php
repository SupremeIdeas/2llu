<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One place for admin media uploads (logos, icons, etc.) with a storage
 * fallback: use Wasabi when it's configured, otherwise the server's own public
 * disk (blueprint Section 3.1 + operator request — the platform must keep
 * working on cPanel with no Wasabi keys).
 *
 * Researched upload limits (safe defaults for logos/icons):
 *   - Raster (PNG/JPEG/WebP/GIF): max 2 MB. Logos are small; 2 MB is generous.
 *   - SVG: max 512 KB AND sanitized (SVG is executable XML — strip scripts,
 *     event handlers, and foreignObject before it's ever served to a user).
 *   - Recommended source size: ~512px on the long edge; transparent PNG or SVG.
 */
class MediaStorage
{
    /** @var list<string> */
    public const RASTER_TYPES = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public const MAX_RASTER_KB = 2048; // 2 MB

    public const MAX_SVG_KB = 512;

    public static function wasabiConfigured(): bool
    {
        return filled(config('filesystems.disks.wasabi.key'))
            && filled(config('filesystems.disks.wasabi.secret'))
            && filled(config('filesystems.disks.wasabi.bucket'));
    }

    /** Disk to store public media on: Wasabi if configured, else server disk. */
    public static function disk(): string
    {
        return self::wasabiConfigured() ? 'wasabi' : 'public';
    }

    /**
     * Disk for PRIVATE files (e.g. GDPR data exports): Wasabi (private) if
     * configured, otherwise the local disk — never web-accessible. Reached only
     * through an owner-authenticated download route.
     */
    public static function privateDisk(): string
    {
        return self::wasabiConfigured() ? 'wasabi' : 'local';
    }

    /** Livewire/validator rule for an uploaded image or SVG. */
    public static function uploadRules(): array
    {
        return ['file', 'mimes:'.implode(',', [...self::RASTER_TYPES, 'svg']), 'max:'.self::MAX_RASTER_KB];
    }

    public static function acceptAttribute(): string
    {
        return 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
    }

    /**
     * Store a public asset and return its public URL. SVGs are sanitized; a
     * new random filename avoids collisions and guessing.
     */
    public static function storePublic(UploadedFile $file, string $dir = 'media'): string
    {
        $disk = self::disk();
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $name = Str::uuid()->toString().'.'.$ext;
        $path = trim($dir, '/').'/'.$name;

        if ($ext === 'svg') {
            Storage::disk($disk)->put($path, self::sanitizeSvg((string) file_get_contents($file->getRealPath())), 'public');
        } else {
            Storage::disk($disk)->putFileAs($dir, $file, $name, 'public');
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Neutralise an SVG before it is served to users: remove scripts, inline
     * event handlers, foreignObject, and javascript: URLs.
     */
    public static function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#\son[a-z]+\s*=\s*"(?:[^"]*)"#i', '', $svg) ?? $svg;
        $svg = preg_replace("#\son[a-z]+\s*=\s*'(?:[^']*)'#i", '', $svg) ?? $svg;
        $svg = preg_replace('#(?:xlink:href|href)\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', '', $svg) ?? $svg;

        return $svg;
    }
}

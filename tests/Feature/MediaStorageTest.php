<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\MediaStorage;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_the_server_public_disk_when_wasabi_is_not_configured(): void
    {
        config(['filesystems.disks.wasabi.key' => null, 'filesystems.disks.wasabi.bucket' => null]);

        $this->assertFalse(MediaStorage::wasabiConfigured());
        $this->assertSame('public', MediaStorage::disk());
    }

    public function test_uses_wasabi_when_it_is_configured(): void
    {
        config([
            'filesystems.disks.wasabi.key' => 'k',
            'filesystems.disks.wasabi.secret' => 's',
            'filesystems.disks.wasabi.bucket' => 'b',
        ]);

        $this->assertTrue(MediaStorage::wasabiConfigured());
        $this->assertSame('wasabi', MediaStorage::disk());
    }

    public function test_livewire_temp_upload_disk_follows_the_wasabi_or_local_resolution(): void
    {
        // The root cause of "uploads fail sitewide when Wasabi keys are unset":
        // Livewire stores every upload to a temp disk BEFORE MediaStorage runs.
        // AppServiceProvider::boot() pins that temp disk to MediaStorage::disk(),
        // so with zero Wasabi keys it is the local `public` disk (always works),
        // and it upgrades to `wasabi` automatically once keys are present.
        config(['filesystems.disks.wasabi.key' => null, 'filesystems.disks.wasabi.bucket' => null]);
        (new AppServiceProvider($this->app))->boot();
        $this->assertSame('public', config('livewire.temporary_file_upload.disk'));

        config([
            'filesystems.disks.wasabi.key' => 'k',
            'filesystems.disks.wasabi.secret' => 's',
            'filesystems.disks.wasabi.bucket' => 'b',
        ]);
        (new AppServiceProvider($this->app))->boot();
        $this->assertSame('wasabi', config('livewire.temporary_file_upload.disk'));
    }

    public function test_stores_a_png_on_the_public_disk_and_returns_a_url(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        $url = MediaStorage::storePublic(UploadedFile::fake()->image('logo.png', 200, 80), 'splash');

        $this->assertStringContainsString('/storage/splash/', $url);
        $this->assertSame(1, count(Storage::disk('public')->allFiles('splash')));
    }

    public function test_sanitizes_svg_uploads_removing_scripts_and_handlers(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(2)</script><rect width="10" height="10"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $dirty);

        $url = MediaStorage::storePublic($file, 'splash');
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringContainsString('<rect', $stored); // real content kept
    }

    public function test_svg_sanitizer_strips_unquoted_and_obfuscated_xss_vectors(): void
    {
        // The regression: unquoted event handlers and a self-closing <script>
        // are the payloads a quotes-only rule missed. Also cover <style> and a
        // whitespace-obfuscated javascript: href.
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" onload=alert(1)>'
            .'<script src="x.js"/>'
            .'<style>@import url(javascript:alert(3))</style>'
            .'<a href="jav ascript:alert(4)"><rect width="10" height="10"/></a>'
            .'</svg>';

        $clean = MediaStorage::sanitizeSvg($dirty);

        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<style', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', str_replace(' ', '', $clean));
        $this->assertStringContainsString('<rect', $clean); // real content kept
    }

    public function test_default_admin_is_seeded_by_the_database_seeder(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $admin = User::where('email', DefaultAdminSeeder::EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
    }
}

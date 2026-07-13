<?php

namespace Tests\Feature;

use App\Support\MediaStorage;
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

    public function test_default_admin_is_seeded_by_the_database_seeder(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $admin = \App\Models\User::where('email', \Database\Seeders\DefaultAdminSeeder::EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
    }
}

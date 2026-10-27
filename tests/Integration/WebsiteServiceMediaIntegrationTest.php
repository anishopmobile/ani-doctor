<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\CatalogService;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WebsiteServiceMediaIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_archive_cleans_private_image_only_after_committed_catalog_mutation(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = User::create(['mobile' => '09120000995']);
        $admin->forceFill(['mobile_verified_at' => now()])->save();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $service = Service::create([
            'name' => 'خدمت', 'price_irr' => 1000, 'duration_minutes' => 30,
            'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true,
        ]);
        $url = route('website.media.store', ['service', $service->id]);
        $this->actingAs($admin)->post($url, [
            'image' => UploadedFile::fake()->image('service.jpg'), 'alt_text' => 'تصویر',
        ])->assertSessionHasNoErrors();
        $media = WebsiteMedia::firstOrFail();
        DB::beginTransaction();
        app(CatalogService::class)->archive($service, $admin->id);
        Storage::disk('local')->assertExists($media->path);
        DB::rollBack();
        Storage::disk('local')->assertExists($media->path);
        $this->assertDatabaseHas('website_media', ['id' => $media->id]);
        app(CatalogService::class)->archive($service, $admin->id);
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseMissing('website_media', ['id' => $media->id]);
        $this->post($url, [
            'image' => UploadedFile::fake()->image('orphan.jpg'), 'alt_text' => 'تصویر',
        ])->assertNotFound();
        $this->assertDatabaseCount('website_media', 0);
    }
}

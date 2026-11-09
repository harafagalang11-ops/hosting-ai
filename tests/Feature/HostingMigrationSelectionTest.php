<?php

namespace Tests\Feature;

use App\Models\MigrationEndpoint;
use App\Models\User;
use App\Models\Website;
use App\Services\LocalhostMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingMigrationSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function createEndpoint(User $user, string $name): MigrationEndpoint
    {
        return MigrationEndpoint::create([
            'user_id' => $user->id,
            'name' => $name,
            'host' => strtolower($name) . '.example.test',
            'port' => 22,
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'credential' => 'test-credential',
        ]);
    }

    public function test_selected_source_target_and_website_are_saved_and_passed_to_service(): void
    {
        $user = User::factory()->create();

        $source = $this->createEndpoint($user, 'Hosting Sumber');
        $target = $this->createEndpoint($user, 'Hosting Tujuan');

        $website = Website::create([
            'migration_endpoint_id' => $source->id,
            'user_id' => $user->id,
            'name' => 'Website Uji',
            'url' => 'https://website-uji.example.test',
            'is_active' => true,
        ]);

        $context = [
            'source_endpoint_id' => $source->id,
            'target_endpoint_id' => $target->id,
            'website_id' => $website->id,
            'website_name' => $website->name,
        ];

        $this->mock(LocalhostMigrationService::class, function ($mock) use ($context) {
            $mock->shouldReceive('migrate')
                ->once()
                ->with($context)
                ->andReturn([
                    'files' => 3,
                    'database' => 'ok',
                    'http_status' => 200,
                    'backup' => 'migration-test.zip',
                    'verification' => 'Berhasil',
                    'cleanup_warning' => null,
                    'source' => '127.0.0.1:8081',
                    'target' => '127.0.0.1:8082',
                    'selection' => $context,
                ]);
        });

        $this->actingAs($user)
            ->post(route('migration.run'), [
                'source_endpoint_id' => $source->id,
                'target_endpoint_id' => $target->id,
                'website_id' => $website->id,
            ])
            ->assertRedirect(route('migration.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('hosting_migrations', [
            'user_id' => $user->id,
            'source_endpoint_id' => $source->id,
            'target_endpoint_id' => $target->id,
            'website_id' => $website->id,
            'website_name' => 'Website Uji',
            'source' => 'Hosting Sumber (hosting sumber.example.test:22)',
            'target' => 'Hosting Tujuan (hosting tujuan.example.test:22)',
            'status' => 'success',
            'file_count' => 3,
            'database_integrity' => 'ok',
            'http_status' => 200,
            'backup_file' => 'migration-test.zip',
        ]);
    }

    public function test_user_cannot_use_another_users_target_endpoint(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $source = $this->createEndpoint($user, 'Hosting Saya');
        $target = $this->createEndpoint($otherUser, 'Hosting Orang Lain');

        $website = Website::create([
            'migration_endpoint_id' => $source->id,
            'user_id' => $user->id,
            'name' => 'Website Saya',
            'url' => 'https://website-saya.example.test',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('migration.run'), [
                'source_endpoint_id' => $source->id,
                'target_endpoint_id' => $target->id,
                'website_id' => $website->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('hosting_migrations', 0);
    }

    public function test_user_cannot_migrate_website_owned_by_different_source_endpoint(): void
    {
        $user = User::factory()->create();

        $source = $this->createEndpoint($user, 'Hosting Sumber');
        $target = $this->createEndpoint($user, 'Hosting Tujuan');

        $website = Website::create([
            'migration_endpoint_id' => $target->id,
            'user_id' => $user->id,
            'name' => 'Website Bukan Milik Sumber',
            'url' => 'https://website-lain.example.test',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('migration.run'), [
                'source_endpoint_id' => $source->id,
                'target_endpoint_id' => $target->id,
                'website_id' => $website->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('hosting_migrations', 0);
    }

    public function test_user_cannot_migrate_another_users_website_attached_to_own_endpoint(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $source = $this->createEndpoint($user, 'Hosting Sumber');
        $target = $this->createEndpoint($user, 'Hosting Tujuan');

        $website = Website::create([
            'migration_endpoint_id' => $source->id,
            'user_id' => $otherUser->id,
            'name' => 'Website Milik Pengguna Lain',
            'url' => 'https://website-orang-lain.example.test',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('migration.index'))
            ->assertOk()
            ->assertDontSee('Website Milik Pengguna Lain');

        $this->actingAs($user)
            ->post(route('migration.run'), [
                'source_endpoint_id' => $source->id,
                'target_endpoint_id' => $target->id,
                'website_id' => $website->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('hosting_migrations', 0);
    }

    public function test_migration_page_displays_only_active_websites_from_user_endpoints(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $source = $this->createEndpoint($user, 'Hosting Sumber');
        $target = $this->createEndpoint($user, 'Hosting Tujuan');
        $otherEndpoint = $this->createEndpoint($otherUser, 'Hosting Pengguna Lain');

        Website::create([
            'migration_endpoint_id' => $source->id,
            'user_id' => $user->id,
            'name' => 'Website Aktif',
            'url' => 'https://aktif.example.test',
            'document_root' => '/home/user/public_html',
            'is_active' => true,
        ]);

        Website::create([
            'migration_endpoint_id' => $target->id,
            'user_id' => $user->id,
            'name' => 'Website Tanpa Document Root',
            'url' => 'https://tanpa-root.example.test',
            'document_root' => null,
            'is_active' => true,
        ]);

        Website::create([
            'migration_endpoint_id' => $source->id,
            'user_id' => $user->id,
            'name' => 'Website Nonaktif',
            'url' => 'https://nonaktif.example.test',
            'document_root' => '/home/user/nonaktif',
            'is_active' => false,
        ]);

        Website::create([
            'migration_endpoint_id' => $otherEndpoint->id,
            'user_id' => $otherUser->id,
            'name' => 'Website Pengguna Lain',
            'url' => 'https://orang-lain.example.test',
            'document_root' => '/home/other/public_html',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('migration.index'))
            ->assertOk()
            ->assertSee('Website Aktif')
            ->assertSee('/home/user/public_html')
            ->assertSee('Website Tanpa Document Root')
            ->assertSee('Document Root belum diisi')
            ->assertDontSee('Website Nonaktif')
            ->assertDontSee('Website Pengguna Lain');
    }

}

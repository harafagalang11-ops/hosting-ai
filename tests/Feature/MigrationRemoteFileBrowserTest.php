<?php

namespace Tests\Feature;

use App\Models\MigrationEndpoint;
use App\Models\User;
use App\Services\MigrationRemoteFileBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MigrationRemoteFileBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function createEndpoint(
        User $user,
        string $type = 'sftp'
    ): MigrationEndpoint {
        return MigrationEndpoint::create([
            'user_id' => $user->id,
            'name' => 'Hosting Uji',
            'host' => 'hosting.example.test',
            'port' => 22,
            'connection_type' => $type,
            'username' => 'test-user',
            'credential' => 'secret-test-password',
        ]);
    }

    public function test_owner_can_view_their_sftp_directory(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) use ($endpoint) {
            $mock->shouldReceive('listDirectory')
                ->once()
                ->with(
                    \Mockery::on(fn ($value) => $value->id === $endpoint->id),
                    'public_html'
                )
                ->andReturn([
                    'path' => 'public_html',
                    'entries' => [
                        [
                            'name' => 'assets',
                            'type' => 'directory',
                            'size' => 0,
                            'modified_at' => null,
                            'permissions' => '0755',
                        ],
                        [
                            'name' => 'index.php',
                            'type' => 'file',
                            'size' => 1024,
                            'modified_at' => null,
                            'permissions' => '0644',
                        ],
                    ],
                    'total' => 2,
                    'truncated' => false,
                ]);
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', [
                'id' => $endpoint->id,
                'path' => 'public_html',
            ]))
            ->assertOk()
            ->assertViewIs('migration.files')
            ->assertSee('Penjelajah File Hosting')
            ->assertSee('public_html')
            ->assertSee('assets')
            ->assertSee('index.php')
            ->assertSee('Mode hanya-baca');
    }

    public function test_user_cannot_view_another_users_sftp_directory(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $endpoint = $this->createEndpoint($otherUser);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) {
            $mock->shouldNotReceive('listDirectory');
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', $endpoint->id))
            ->assertNotFound();
    }

    public function test_guest_cannot_view_sftp_directory(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->get(route('migration-endpoints.files', $endpoint->id))
            ->assertRedirect(route('login'));
    }

    public function test_non_sftp_endpoint_cannot_open_file_browser(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user, 'ssh');

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) {
            $mock->shouldNotReceive('listDirectory');
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', $endpoint->id))
            ->assertForbidden();
    }

    public function test_directory_error_is_displayed_without_crashing_page(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) use ($endpoint) {
            $mock->shouldReceive('listDirectory')
                ->once()
                ->andThrow(new RuntimeException(
                    'Direktori tidak dapat dibaca. Periksa path dan izin akses akun SFTP.'
                ));
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', [
                'id' => $endpoint->id,
                'path' => 'private',
            ]))
            ->assertOk()
            ->assertSee('Direktori tidak dapat dibaca')
            ->assertSee('Kembali ke Kelola Hosting');
    }


    private function createDemoEndpoint(User $user): MigrationEndpoint
    {
        $endpoint = $this->createEndpoint($user);
        $endpoint->update([
            'name' => 'SFTP Demo Lokal',
            'host' => 'sftp-demo.invalid',
        ]);

        return $endpoint;
    }

    public function test_local_sftp_demo_displays_sample_root_without_network_connection(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) {
            $mock->shouldNotReceive('listDirectory');
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', $endpoint->id))
            ->assertOk()
            ->assertViewIs('migration.files')
            ->assertSee('Mode Simulasi SFTP.')
            ->assertSee('public_html')
            ->assertSee('storage')
            ->assertSee('backup.zip');
    }

    public function test_local_sftp_demo_can_navigate_into_sample_subdirectory(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) {
            $mock->shouldNotReceive('listDirectory');
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', [
                'id' => $endpoint->id,
                'path' => 'public_html/assets',
            ]))
            ->assertOk()
            ->assertSee('Mode Simulasi SFTP.')
            ->assertSee('public_html/assets')
            ->assertSee('logo.png')
            ->assertSee('css')
            ->assertSee('js');
    }

    public function test_local_sftp_demo_rejects_parent_directory_traversal(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->mock(MigrationRemoteFileBrowser::class, function ($mock) {
            $mock->shouldNotReceive('listDirectory');
        });

        $this->actingAs($user)
            ->get(route('migration-endpoints.files', [
                'id' => $endpoint->id,
                'path' => '../',
            ]))
            ->assertOk()
            ->assertSee('Direktori tidak ditemukan atau path tidak valid.');
    }


    public function test_local_demo_inventory_calculates_directory_and_file_totals(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->actingAs($user)
            ->get(route('migration-endpoints.inventory', $endpoint->id))
            ->assertOk()
            ->assertViewIs('migration.inventory')
            ->assertSee('Inventaris File Hosting')
            ->assertSee('Jumlah folder')
            ->assertSee('Jumlah file')
            ->assertSee('238,720 byte')
            ->assertSee('public_html/assets/css/app.css')
            ->assertSee('storage/logs/application.log');
    }

    public function test_real_sftp_endpoint_cannot_access_demo_inventory(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->actingAs($user)
            ->get(route('migration-endpoints.inventory', $endpoint->id))
            ->assertNotFound();
    }

    public function test_user_cannot_view_another_users_demo_inventory(): void
    {
        $this->app['env'] = 'local';

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($owner);

        $this->actingAs($otherUser)
            ->get(route('migration-endpoints.inventory', $endpoint->id))
            ->assertNotFound();
    }

    public function test_guest_cannot_view_demo_inventory(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->get(route('migration-endpoints.inventory', $endpoint->id))
            ->assertRedirect(route('login'));
    }

    public function test_demo_inventory_is_unavailable_outside_local_environment(): void
    {
        $this->app['env'] = 'testing';

        $user = User::factory()->create();
        $endpoint = $this->createDemoEndpoint($user);

        $this->actingAs($user)
            ->get(route('migration-endpoints.inventory', $endpoint->id))
            ->assertNotFound();
    }
}

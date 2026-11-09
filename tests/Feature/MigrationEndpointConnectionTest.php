<?php

namespace Tests\Feature;

use App\Models\MigrationEndpoint;
use App\Models\User;
use App\Services\MigrationConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationEndpointConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function createEndpoint(User $user): MigrationEndpoint
    {
        return MigrationEndpoint::create([
            'user_id' => $user->id,
            'name' => 'Hosting Uji',
            'host' => 'hosting.example.test',
            'port' => 22,
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'credential' => 'secret-test-password',
        ]);
    }

    private function endpointPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hosting Baru',
            'host' => 'hosting.example.test',
            'connection_type' => 'sftp',
            'username' => 'test-user',
            'password' => 'secret-test-password',
        ], $overrides);
    }

    public function test_owner_can_test_their_hosting_connection(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->mock(MigrationConnectionService::class, function ($mock) use ($endpoint) {
            $mock->shouldReceive('test')
                ->once()
                ->with(\Mockery::on(
                    fn ($value) => $value->id === $endpoint->id
                ))
                ->andReturn([
                    'success' => true,
                    'message' => 'Koneksi SFTP berhasil. Autentikasi server terverifikasi.',
                ]);
        });

        $this->actingAs($user)
            ->post(route('migration-endpoints.test-connection', $endpoint->id))
            ->assertRedirect(route('migration-endpoints.index'))
            ->assertSessionHas('success');
    }

    public function test_user_cannot_test_another_users_hosting_connection(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $endpoint = $this->createEndpoint($otherUser);

        $this->mock(MigrationConnectionService::class, function ($mock) {
            $mock->shouldNotReceive('test');
        });

        $this->actingAs($user)
            ->post(route('migration-endpoints.test-connection', $endpoint->id))
            ->assertNotFound();
    }

    public function test_guest_cannot_test_hosting_connection(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->post(route('migration-endpoints.test-connection', $endpoint->id))
            ->assertRedirect(route('login'));
    }

    public function test_cpanel_api_endpoint_uses_default_port_2083(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('migration-endpoints.store'), $this->endpointPayload([
                'connection_type' => 'cpanel_api',
            ]))
            ->assertRedirect(route('migration-endpoints.index'))
            ->assertSessionHasNoErrors();

        $endpoint = MigrationEndpoint::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('cpanel_api', $endpoint->connection_type);
        $this->assertSame(2083, $endpoint->port);
    }

    public function test_ssh_and_sftp_endpoints_use_default_port_22(): void
    {
        $user = User::factory()->create();

        foreach (['ssh', 'sftp'] as $type) {
            $this->actingAs($user)
                ->post(route('migration-endpoints.store'), $this->endpointPayload([
                    'name' => 'Hosting '.strtoupper($type),
                    'connection_type' => $type,
                ]))
                ->assertRedirect(route('migration-endpoints.index'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            2,
            MigrationEndpoint::where('user_id', $user->id)
                ->where('port', 22)
                ->count()
        );
    }

    public function test_blank_password_during_update_preserves_existing_credential(): void
    {
        $user = User::factory()->create();
        $endpoint = $this->createEndpoint($user);

        $this->actingAs($user)
            ->put(route('migration-endpoints.update', $endpoint->id), $this->endpointPayload([
                'name' => 'Hosting Diperbarui',
                'password' => '',
            ]))
            ->assertRedirect(route('migration-endpoints.index'))
            ->assertSessionHasNoErrors();

        $endpoint->refresh();

        $this->assertSame('Hosting Diperbarui', $endpoint->name);
        $this->assertSame('secret-test-password', $endpoint->credential);
        $this->assertSame(22, $endpoint->port);
    }

    public function test_credential_is_encrypted_in_database(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('migration-endpoints.store'), $this->endpointPayload())
            ->assertRedirect(route('migration-endpoints.index'))
            ->assertSessionHasNoErrors();

        $endpoint = MigrationEndpoint::where('user_id', $user->id)->firstOrFail();

        $storedCredential = DB::table('migration_endpoints')
            ->where('id', $endpoint->id)
            ->value('credential');

        $this->assertNotSame('secret-test-password', $storedCredential);
        $this->assertSame('secret-test-password', $endpoint->credential);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(User $user, string $name = 'Website Uji'): Website
    {
        return $user->websites()->create([
            'name' => $name,
            'url' => 'https://' . strtolower(str_replace(' ', '-', $name)) . '.example.com',
            'is_active' => true,
        ]);
    }

    public function test_user_only_sees_their_own_websites(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myWebsite = $this->createWebsite($user, 'Website Saya');
        $this->createWebsite($otherUser, 'Website Orang Lain');

        $this->actingAs($user)
            ->get(route('websites.index'))
            ->assertOk()
            ->assertViewHas('websites', function ($websites) use ($myWebsite) {
                return $websites->contains('id', $myWebsite->id)
                    && $websites->count() === 1;
            });
    }

    public function test_user_cannot_edit_another_users_website(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser);

        $this->actingAs($user)
            ->get(route('websites.edit', $website))
            ->assertNotFound();
    }

    public function test_user_cannot_update_another_users_website(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser);

        $this->actingAs($user)
            ->put(route('websites.update', $website), [
                'name' => 'Website Diubah',
                'url' => 'https://website-diubah.example.com',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'name' => $website->name,
        ]);
    }

    public function test_user_cannot_toggle_another_users_website(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser);

        $this->actingAs($user)
            ->post(route('websites.toggle', $website))
            ->assertNotFound();

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_delete_another_users_website(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser);

        $this->actingAs($user)
            ->delete(route('websites.destroy', $website))
            ->assertNotFound();

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'deleted_at' => null,
        ]);
    }

    public function test_new_website_is_automatically_owned_by_logged_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('websites.store'), [
                'name' => 'Website Baru',
                'url' => 'https://website-baru.example.com',
            ])
            ->assertRedirect(route('websites.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'name' => 'Website Baru',
            'url' => 'https://website-baru.example.com',
        ]);
    }
}

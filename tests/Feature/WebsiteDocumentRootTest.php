<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteDocumentRootTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_website_with_valid_document_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('websites.store'), [
                'name' => 'Website Document Root',
                'url' => 'https://document-root.example.com',
                'document_root' => '/home/user/public_html',
            ])
            ->assertRedirect(route('websites.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'name' => 'Website Document Root',
            'document_root' => '/home/user/public_html',
        ]);
    }

    public function test_user_can_update_website_document_root(): void
    {
        $user = User::factory()->create();

        $website = $user->websites()->create([
            'name' => 'Website Lama',
            'url' => 'https://website-lama.example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('websites.update', $website), [
                'name' => 'Website Diperbarui',
                'url' => 'https://website-baru.example.com',
                'document_root' => '/home/user/new_public_html',
            ])
            ->assertRedirect(route('websites.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'document_root' => '/home/user/new_public_html',
        ]);
    }

    public function test_document_root_can_be_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('websites.store'), [
                'name' => 'Website Tanpa Root',
                'url' => 'https://tanpa-root.example.com',
                'document_root' => '',
            ])
            ->assertRedirect(route('websites.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'name' => 'Website Tanpa Root',
            'document_root' => null,
        ]);
    }

    public function test_unsafe_document_root_paths_are_rejected(): void
    {
        $user = User::factory()->create();

        $unsafePaths = [
            'relative/path',
            '/',
            '/home/user/../other',
            '/home//user',
            '/home\\user',
        ];

        foreach ($unsafePaths as $index => $path) {
            $this->actingAs($user)
                ->post(route('websites.store'), [
                    'name' => 'Website Invalid '.$index,
                    'url' => 'https://invalid-'.$index.'.example.com',
                    'document_root' => $path,
                ])
                ->assertSessionHasErrors('document_root');

            $this->assertDatabaseMissing('websites', [
                'name' => 'Website Invalid '.$index,
            ]);
        }
    }
}

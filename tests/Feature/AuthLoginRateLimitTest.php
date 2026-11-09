<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_limited_after_five_failed_attempts(): void
    {
        $credentials = [
            'email' => 'limit-test@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $credentials)
                ->assertRedirect();
        }

        $this->post('/login', $credentials)
            ->assertTooManyRequests();
    }

    public function test_different_email_has_a_separate_login_limit(): void
    {
        $credentials = [
            'email' => 'first-user@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $credentials)
                ->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'second-user@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect();
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rate_limiting()
    {
        // Test that registration is rate limited after 10 attempts
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/register', [
                'name' => 'Test User ' . $i,
                'email' => 'test' . $i . '@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'creator',
                'whatsapp' => '+1234567890'
            ]);

            if ($i < 9) {
                // First 9 attempts should succeed or fail for validation reasons, not rate limiting
                $this->assertNotEquals(429, $response->status());
            } else {
                // 10th attempt should be rate limited
                $this->assertEquals(429, $response->status());
                $this->assertStringContainsString('Muitas tentativas de registro', $response->json('message'));
                $this->assertArrayHasKey('retry_after', $response->json());
            }
        }
    }

    public function test_login_rate_limiting()
    {
        // Create a user first
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Test that login is rate limited after 20 attempts
        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword' // Use wrong password to trigger rate limiting
            ]);

            if ($i < 19) {
                // First 19 attempts should fail for wrong password, not rate limiting
                $this->assertNotEquals(429, $response->status());
            } else {
                // 20th attempt should be rate limited
                $this->assertEquals(429, $response->status());
                $this->assertStringContainsString('Muitas tentativas de login', $response->json('message'));
                $this->assertArrayHasKey('retry_after', $response->json());
            }
        }
    }

    public function test_password_reset_rate_limiting()
    {
        // Test that password reset is rate limited after 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/forgot-password', [
                'email' => 'test' . $i . '@example.com'
            ]);

            if ($i < 4) {
                // First 4 attempts should succeed or fail for validation reasons, not rate limiting
                $this->assertNotEquals(429, $response->status());
            } else {
                // 5th attempt should be rate limited
                $this->assertEquals(429, $response->status());
                $this->assertStringContainsString('Muitas tentativas de redefinição de senha', $response->json('message'));
                $this->assertArrayHasKey('retry_after', $response->json());
            }
        }
    }

    public function test_rate_limiting_headers()
    {
        // Test that rate limiting headers are included in responses
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'creator',
            'whatsapp' => '+1234567890'
        ]);

        // Should have rate limiting headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }

    public function test_rate_limiting_reset_after_success()
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Make several failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // Now try a successful login
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Should succeed
        $this->assertEquals(200, $response->status());
        
        // Rate limiting should be cleared for this user/email combination
        $this->assertTrue($response->json('success'));
    }
} 
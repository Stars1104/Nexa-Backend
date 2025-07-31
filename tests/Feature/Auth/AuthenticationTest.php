<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_api_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'role',
                        'whatsapp',
                        'avatar_url',
                        'bio',
                        'company_name',
                        'student_verified',
                        'student_expires_at',
                        'gender',
                        'state',
                        'language',
                        'has_premium',
                        'premium_expires_at',
                        'free_trial_expires_at',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'token_type' => 'Bearer',
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_users_can_not_authenticate_with_invalid_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJson([
                    'id' => $user->id,
                    'email' => $user->email,
                ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_users_can_logout_with_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);

        // Try to access protected route with the same token (should fail)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validates_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_user_response_includes_all_expected_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'creator',
            'has_premium' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $userData = $response->json('user');
        
        $this->assertEquals($user->id, $userData['id']);
        $this->assertEquals($user->name, $userData['name']);
        $this->assertEquals($user->email, $userData['email']);
        $this->assertEquals($user->role, $userData['role']);
        $this->assertEquals($user->has_premium, $userData['has_premium']);
        $this->assertEquals($user->student_verified, $userData['student_verified']);
    }

    public function test_login_works_with_different_user_roles(): void
    {
        $creator = User::factory()->create([
            'email' => 'creator@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'creator',
        ]);

        $brand = User::factory()->create([
            'email' => 'brand@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'brand',
        ]);

        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        // Test creator login
        $response = $this->postJson('/api/login', [
            'email' => 'creator@example.com',
            'password' => 'Password123!',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('creator', $response->json('user.role'));

        // Test brand login
        $response = $this->postJson('/api/login', [
            'email' => 'brand@example.com',
            'password' => 'Password123!',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('brand', $response->json('user.role'));

        // Test admin login
        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('admin', $response->json('user.role'));
    }
}

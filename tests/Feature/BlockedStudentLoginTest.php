<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BlockedStudentLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_student_cannot_login()
    {
        // Create a student user
        $user = User::factory()->create([
            'role' => 'student',
            'student_verified' => true,
            'email_verified_at' => null, // Blocked user
        ]);

        // Attempt to login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Should be blocked
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $response->assertJson([
            'errors' => [
                'email' => ['Sua conta foi bloqueada. Entre em contato com o suporte para mais informações.']
            ]
        ]);
    }

    public function test_removed_student_cannot_login()
    {
        // Create a student user
        $user = User::factory()->create([
            'role' => 'student',
            'student_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Soft delete the user (simulate removal)
        $user->delete();

        // Attempt to login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Should be blocked
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $response->assertJson([
            'errors' => [
                'email' => ['Sua conta foi removida da plataforma. Entre em contato com o suporte para mais informações.']
            ]
        ]);
    }

    public function test_active_student_can_login()
    {
        // Create an active student user
        $user = User::factory()->create([
            'role' => 'student',
            'student_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Attempt to login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Should be successful
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'token',
            'token_type',
            'user'
        ]);
    }

    public function test_blocked_student_cannot_access_protected_routes()
    {
        // Create a blocked student user
        $user = User::factory()->create([
            'role' => 'student',
            'student_verified' => true,
            'email_verified_at' => null, // Blocked user
        ]);

        // Create a token for the user (simulate they somehow got a token)
        $token = $user->createToken('test-token')->plainTextToken;

        // Attempt to access protected route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        // Should be blocked
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Sua conta foi bloqueada. Entre em contato com o suporte para mais informações.'
        ]);
    }

    public function test_removed_student_cannot_access_protected_routes()
    {
        // Create a student user
        $user = User::factory()->create([
            'role' => 'student',
            'student_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Create a token for the user
        $token = $user->createToken('test-token')->plainTextToken;

        // Soft delete the user (simulate removal)
        $user->delete();

        // Attempt to access protected route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        // Should be blocked
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Sua conta foi removida da plataforma. Entre em contato com o suporte para mais informações.'
        ]);
    }
}

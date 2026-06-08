<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create distributor
        \App\Models\Distributor::create([
            'code_customer' => 'DUMMY001',
            'name' => 'Dummy Distributor',
            'status' => 1,
        ]);

        // Create standard active user
        $this->user = User::create([
            'name' => 'Active User',
            'username' => 'activeuser',
            'email' => 'active@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'DUMMY001',
            'is_active' => true,
        ]);
    }

    /**
     * Test login success.
     */
    public function test_login_success(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/auth/login', [
            'username' => 'activeuser',
            'code_customer' => 'DUMMY001',
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'email', 'code_customer', 'is_active'],
                    'access_token',
                    'token_type',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'user' => [
                        'username' => 'activeuser',
                        'code_customer' => 'DUMMY001',
                    ]
                ]
            ]);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'LOGIN',
        ]);
    }

    /**
     * Test login failure due to invalid credentials.
     */
    public function test_login_failure_invalid_credentials(): void
    {
        $response = $this->postJson('/api/distributor-channel/v1/auth/login', [
            'username' => 'activeuser',
            'code_customer' => 'DUMMY001',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'username'
                ]
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation Error',
            ]);
    }

    /**
     * Test login failure due to inactive user.
     */
    public function test_login_failure_inactive_user(): void
    {
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'username' => 'inactiveuser',
            'email' => 'inactive@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'DUMMY001',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/distributor-channel/v1/auth/login', [
            'username' => 'inactiveuser',
            'code_customer' => 'DUMMY001',
            'password' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation Error',
            ])
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test logout success.
     */
    public function test_logout_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout berhasil.',
                'data' => []
            ]);

        // Assert token is deleted
        $this->assertEquals(0, $this->user->tokens()->count());

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'LOGOUT',
        ]);
    }

    /**
     * Test refresh token success.
     */
    public function test_refresh_token_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token berhasil diperbarui.',
            ]);

        // Assert old token deleted and new token exists
        $this->assertEquals(1, $this->user->tokens()->count());

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'REFRESH_TOKEN',
        ]);
    }

    /**
     * Test change password success.
     */
    public function test_change_password_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/auth/change-password', [
            'old_password' => $this->password,
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password berhasil diubah.',
            ]);

        // Verify password updated in DB
        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'CHANGE_PASSWORD',
        ]);
    }

    /**
     * Test change password fails when old password is wrong.
     */
    public function test_change_password_wrong_old_password(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/auth/change-password', [
            'old_password' => 'wrongoldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['old_password']);
    }

    /**
     * Test change password fails when new password is same as old password.
     */
    public function test_change_password_same_new_password(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/auth/change-password', [
            'old_password' => $this->password,
            'new_password' => $this->password,
            'new_password_confirmation' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }
}

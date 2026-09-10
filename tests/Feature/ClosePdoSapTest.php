<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClosePdoSapTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Production Admin',
            'username' => 'prodadmin',
            'email' => 'prodadmin@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_close_pdo_sap_endpoint(): void
    {
        Http::fake([
            '*/api/closepdo' => Http::response([
                'ErrorCode' => 0,
                'Message' => 'Success close PDO',
            ], 200),
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $payload = [
            'DocEntry' => '123123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/close-pdo-sap', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ErrorCode', 0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/closepdo') &&
                $request['DocEntry'] === '123123' &&
                $request['AddonId'] === 2 &&
                $request['UserId'] === $this->user->id;
        });
    }

    public function test_close_pdo_sap_validation_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/close-pdo-sap', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

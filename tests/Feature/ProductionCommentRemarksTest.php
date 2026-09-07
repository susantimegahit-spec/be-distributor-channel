<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionCommentRemarksTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'prodedit@example.com',
        ]);
    }

    /**
     * Test successful edit comment for Issue for Production.
     */
    public function test_edit_comment_issue_for_production_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/EditCommentIssueForProduction' => function ($request) {
                $body = json_decode($request->body(), true);
                if (
                    isset($body['DocEntry']) && $body['DocEntry'] === '123' &&
                    isset($body['Comment']) && $body['Comment'] === 'perbaikan coment'
                ) {
                    return Http::response([
                        'ErrorCode' => 0,
                        'Message' => 'Success update comment',
                        'Result' => ['DocEntry' => '123']
                    ], 200);
                }
                return Http::response(['ErrorCode' => 1, 'Message' => 'Invalid parameters'], 200);
            }
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/issues/sap/edit-comment', [
            'DocEntry' => '123',
            'Comment'  => 'perbaikan coment',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.doc_entry', '123')
            ->assertJsonPath('data.comment', 'perbaikan coment');

        // Test alias endpoint
        $responseAlias = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/edit-comment-issue-for-production', [
            'doc_entry' => '123',
            'comment'   => 'perbaikan coment',
        ]);

        $responseAlias->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test validation error when DocEntry is missing in edit comment issue.
     */
    public function test_edit_comment_issue_validation_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/issues/sap/edit-comment', [
            'Comment' => 'missing doc entry'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 422);
    }

    /**
     * Test successful edit remarks for Production Receipt.
     */
    public function test_edit_receipt_remarks_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        Http::fake([
            '*/api/EditReceiptRemarks' => function ($request) {
                $body = json_decode($request->body(), true);
                if (
                    isset($body['DocEntry']) && $body['DocEntry'] === '456' &&
                    isset($body['Comment']) && $body['Comment'] === 'perbaikan receipt remarks'
                ) {
                    return Http::response([
                        'ErrorCode' => 0,
                        'Message' => 'Success update receipt remarks',
                        'Result' => ['DocEntry' => '456']
                    ], 200);
                }
                return Http::response(['ErrorCode' => 1, 'Message' => 'Invalid parameters'], 200);
            }
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/receipts/sap/edit-remarks', [
            'DocEntry' => '456',
            'Comment'  => 'perbaikan receipt remarks',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.doc_entry', '456')
            ->assertJsonPath('data.comment', 'perbaikan receipt remarks');

        // Test alias endpoint
        $responseAlias = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/edit-receipt-remarks', [
            'doc_entry' => '456',
            'comment'   => 'perbaikan receipt remarks',
        ]);

        $responseAlias->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test validation error when DocEntry is missing in edit receipt remarks.
     */
    public function test_edit_receipt_remarks_validation_error(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/distributor-channel/v1/production/receipts/sap/edit-remarks', [
            'Comment' => 'missing doc entry'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 422);
    }
}

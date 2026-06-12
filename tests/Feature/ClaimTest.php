<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Distributor;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Distributor $distributor;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create distributor
        $this->distributor = Distributor::create([
            'code_customer' => 'C110003074',
            'name' => 'PT XYZ',
            'address' => 'Jl. Dummy No. 123',
            'phone' => '021-12345678',
            'email' => 'info@xyz.com',
            'status' => 1,
        ]);

        // Create user for distributor
        $this->user = User::create([
            'name' => 'Distributor User',
            'username' => 'distuser',
            'email' => 'dist@example.com',
            'password' => Hash::make($this->password),
            'code_customer' => 'C110003074',
            'is_active' => true,
        ]);

        // Create some items for sheet 2 master data
        Item::create([
            'item_code' => 'E65',
            'item_name' => 'TOP 250 M @ 10 KG / BAL',
            'suom_entry' => 1,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 10,
            'status' => 1,
        ]);

        Item::create([
            'item_code' => 'E66',
            'item_name' => 'TOP 500 M @ 20 KG / BAL',
            'suom_entry' => 2,
            'sal_unit_msr' => 'Kg',
            'per_kg' => 20,
            'status' => 1,
        ]);
    }

    /**
     * Test download template excel requires authentication.
     */
    public function test_download_template_requires_auth(): void
    {
        $response = $this->getJson('/api/distributor-channel/v1/claims/template-excel');
        $response->assertStatus(401);
    }

    /**
     * Test download template excel succeeds.
     */
    public function test_download_template_success(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/distributor-channel/v1/claims/template-excel');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel');
        $response->assertHeader('Content-Disposition', 'attachment; filename="template_upload_klaim.xls"');

        $content = $response->getContent();
        
        // Assert XML properties exist
        $this->assertStringContainsString('<?xml version="1.0"?>', $content);
        $this->assertStringContainsString('progid="Excel.Sheet"', $content);
        
        // Assert sheet names exist
        $this->assertStringContainsString('ss:Name="template upload klaim"', $content);
        $this->assertStringContainsString('ss:Name="master data item"', $content);
        
        // Assert headers on sheet 1 exist
        $this->assertStringContainsString('<Data ss:Type="String">Kode Customer</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">Nama Customer</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">Item</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">Nama Item</Data>', $content);
        
        // Assert master data items from database exist in XML
        $this->assertStringContainsString('<Data ss:Type="String">E65</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">TOP 250 M @ 10 KG / BAL</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">E66</Data>', $content);
        $this->assertStringContainsString('<Data ss:Type="String">TOP 500 M @ 20 KG / BAL</Data>', $content);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Picklist;
use App\Models\PicklistGoodsAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PicklistGoodsAttachmentTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('s3');
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);

        $this->user = User::firstOrCreate(
            ['username' => 'logisticofficer'],
            [
                'name'      => 'Logistic Officer',
                'email'     => 'logistics@test.com',
                'password'  => Hash::make('password123'),
                'is_active' => true,
            ]
        );
    }

    protected function createPicklist(): Picklist
    {
        return Picklist::create([
            'picklist_no'   => 'PKL-TEST-' . uniqid(),
            'status'        => Picklist::STATUS_OPEN,
            'shipping_type' => 'Internal',
            'posting_date'  => '2026-11-06',
            'due_date'      => '2026-11-07',
            'license_plate' => 'L 1234 AB',
            'driver_name'   => 'Pak Bambang Driver',
            'checker_name'  => 'Mas Agus Checker',
            'created_by'    => $this->user->id,
        ]);
    }

    public function test_can_upload_multi_barang_masuk_and_barang_keluar_via_dedicated_endpoint(): void
    {
        $picklist = $this->createPicklist();
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Sample 1x1 transparent PNG Base64
        $samplePngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $payload = [
            'barang_masuk' => [
                [
                    'image'     => $samplePngBase64,
                    'file_name' => 'truk_masuk_depan.png',
                    'caption'   => 'Kondisi truk masuk tampak depan',
                ],
                [
                    'image'     => $samplePngBase64,
                    'file_name' => 'segel_masuk.png',
                    'caption'   => 'Pemeriksaan nomor segel masuk',
                ],
            ],
            'barang_keluar' => [
                [
                    'image'     => $samplePngBase64,
                    'file_name' => 'muatan_keluar.png',
                    'caption'   => 'Selesai pemuatan barang rapi',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/attachments", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.barang_masuk')
            ->assertJsonCount(1, 'data.barang_keluar')
            ->assertJsonPath('data.total', 3);

        // Verify records in database
        $attachments = PicklistGoodsAttachment::where('picklist_id', $picklist->id)->orderBy('id')->get();
        $this->assertCount(3, $attachments);

        $masuk1 = $attachments->where('category', PicklistGoodsAttachment::CATEGORY_IN)->first();
        $this->assertEquals('truk_masuk_depan.png', $masuk1->file_name);
        $this->assertEquals('Kondisi truk masuk tampak depan', $masuk1->caption);
        $this->assertNotEmpty($masuk1->attachment_url);
        $this->assertNotEmpty($masuk1->preview_url);

        $keluar1 = $attachments->where('category', PicklistGoodsAttachment::CATEGORY_OUT)->first();
        $this->assertEquals('muatan_keluar.png', $keluar1->file_name);
        $this->assertEquals('Selesai pemuatan barang rapi', $keluar1->caption);

        // Verify files stored in S3 and public storage
        Storage::disk('s3')->assertExists($masuk1->file_path);
        Storage::disk('s3')->assertExists($keluar1->file_path);
        Storage::disk('public')->assertExists($masuk1->file_path);
    }

    public function test_can_upload_multi_uploaded_files_via_form_data(): void
    {
        $picklist = $this->createPicklist();
        $token = $this->user->createToken('test_token')->plainTextToken;

        $fileIn1 = UploadedFile::fake()->image('in_1.jpg', 640, 480);
        $fileIn2 = UploadedFile::fake()->image('in_2.jpg', 640, 480);
        $fileOut1 = UploadedFile::fake()->image('out_1.jpg', 640, 480);

        $payload = [
            'barang_masuk'          => [$fileIn1, $fileIn2],
            'caption_barang_masuk'  => ['Pemeriksaan 1', 'Pemeriksaan 2'],
            'barang_keluar'         => [$fileOut1],
            'caption_barang_keluar' => ['Pemeriksaan Keluar 1'],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/goods-attachments", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.barang_masuk')
            ->assertJsonCount(1, 'data.barang_keluar');

        $attachments = PicklistGoodsAttachment::where('picklist_id', $picklist->id)->get();
        $this->assertCount(3, $attachments);

        foreach ($attachments as $att) {
            Storage::disk('s3')->assertExists($att->file_path);
        }
    }

    public function test_picklist_detail_endpoint_returns_eager_loaded_barang_masuk_and_barang_keluar(): void
    {
        $picklist = $this->createPicklist();
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create sample attachments directly in database
        PicklistGoodsAttachment::create([
            'picklist_id' => $picklist->id,
            'category'    => PicklistGoodsAttachment::CATEGORY_IN,
            'file_path'   => 'goods-attachments/picklists/test_in.jpg',
            'file_name'   => 'test_in.jpg',
            'caption'     => 'Foto Masuk',
            'sort_order'  => 1,
            'created_by'  => $this->user->id,
        ]);

        PicklistGoodsAttachment::create([
            'picklist_id' => $picklist->id,
            'category'    => PicklistGoodsAttachment::CATEGORY_OUT,
            'file_path'   => 'goods-attachments/picklists/test_out.jpg',
            'file_name'   => 'test_out.jpg',
            'caption'     => 'Foto Keluar',
            'sort_order'  => 1,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.barang_masuk')
            ->assertJsonCount(1, 'data.barang_keluar')
            ->assertJsonPath('data.barang_masuk.0.file_name', 'test_in.jpg')
            ->assertJsonPath('data.barang_masuk.0.caption', 'Foto Masuk')
            ->assertJsonPath('data.barang_keluar.0.file_name', 'test_out.jpg')
            ->assertJsonPath('data.barang_keluar.0.caption', 'Foto Keluar');
    }

    public function test_can_preview_and_delete_goods_attachment(): void
    {
        $picklist = $this->createPicklist();
        $token = $this->user->createToken('test_token')->plainTextToken;

        $path = 'goods-attachments/picklists/test_preview.jpg';
        Storage::disk('public')->put($path, 'fake-jpeg-content');
        Storage::disk('s3')->put($path, 'fake-jpeg-content');

        $attachment = PicklistGoodsAttachment::create([
            'picklist_id' => $picklist->id,
            'category'    => PicklistGoodsAttachment::CATEGORY_IN,
            'file_path'   => $path,
            'file_name'   => 'test_preview.jpg',
            'mime_type'   => 'image/jpeg',
            'caption'     => 'Preview test',
            'sort_order'  => 1,
            'created_by'  => $this->user->id,
        ]);

        // Preview route (public / unauthenticated allowed for <img> tags)
        $previewRes = $this->get("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/goods-attachments/{$attachment->id}/preview");
        $previewRes->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg');

        // Delete route
        $deleteRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/distributor-channel/v1/logistic/picklists/{$picklist->id}/goods-attachments/{$attachment->id}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('picklist_goods_attachments', [
            'id' => $attachment->id,
        ], 'pgsql_ekspedisi');
    }
}

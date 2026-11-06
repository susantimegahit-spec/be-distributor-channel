<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PicklistGoodsAttachment extends Model
{
    use HasFactory;

    public const CATEGORY_IN  = 'barang_masuk';
    public const CATEGORY_OUT = 'barang_keluar';

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ekspedisi.picklist_goods_attachments';

    /**
     * Get the table associated with the model (stripping schema in sqlite).
     */
    public function getTable(): string
    {
        $table = parent::getTable();
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            $parts = explode('.', $table);
            return end($parts);
        }
        return $table;
    }

    protected $fillable = [
        'picklist_id',
        'category',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'caption',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'picklist_id' => 'integer',
        'file_size'   => 'integer',
        'sort_order'  => 'integer',
        'created_by'  => 'integer',
    ];

    protected $appends = [
        'attachment_url',
        'preview_url',
        'category_label',
    ];

    /**
     * Public / S3 URL accessor for attachment image.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        if (str_starts_with($this->file_path, 'data:')) {
            return $this->file_path;
        }

        $s3Bucket = config('filesystems.disks.s3.bucket');
        if (!empty($s3Bucket)) {
            try {
                if (Storage::disk('s3')->exists($this->file_path)) {
                    return Storage::disk('s3')->url($this->file_path);
                }
            } catch (\Throwable) {
                // If S3 existence check fails, fall through to preview route or public disk
            }
        }

        if ($this->picklist_id && $this->id) {
            $base = config('app.url') ? rtrim(config('app.url'), '/') : '';
            return "{$base}/api/distributor-channel/v1/logistic/picklists/{$this->picklist_id}/goods-attachments/{$this->id}/preview";
        }

        try {
            if (Storage::disk('public')->exists($this->file_path)) {
                return Storage::disk('public')->url($this->file_path);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Preview URL proxy accessor.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        if (empty($this->id) || empty($this->picklist_id)) {
            return null;
        }

        $base = config('app.url') ? rtrim(config('app.url'), '/') : '';
        return "{$base}/api/distributor-channel/v1/logistic/picklists/{$this->picklist_id}/goods-attachments/{$this->id}/preview";
    }

    /**
     * Readable label for category.
     */
    public function getCategoryLabelAttribute(): string
    {
        return $this->category === self::CATEGORY_OUT ? 'Barang Keluar' : 'Barang Masuk';
    }

    /**
     * Belongs to Picklist.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Belongs to User (uploader).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
        $userModel = config('auth.providers.users.model', User::class);
        $instance = new $userModel();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'created_by', 'id', 'creator');
    }
}

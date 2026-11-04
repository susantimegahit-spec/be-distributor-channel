<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PicklistItemLabelPhoto extends Model
{
    use HasFactory;

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
    protected $table = 'ekspedisi.picklist_item_label_photos';

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
        'picklist_item_id',
        'photo_path',
        'file_name',
        'file_size',
        'mime_type',
        'caption',
        'production_date',
        'created_by',
    ];

    protected $casts = [
        'picklist_id'      => 'integer',
        'picklist_item_id' => 'integer',
        'file_size'        => 'integer',
        'production_date'  => 'date',
        'created_by'       => 'integer',
    ];

    protected $appends = [
        'photo_url',
        'preview_url',
    ];

    /**
     * Absolute public or S3 storage URL.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        if (str_starts_with($this->photo_path, 'http://') || str_starts_with($this->photo_path, 'https://')) {
            return $this->photo_path;
        }

        $s3Bucket = config('filesystems.disks.s3.bucket');
        if (!empty($s3Bucket) && Storage::disk('s3')->exists($this->photo_path)) {
            try {
                return Storage::disk('s3')->url($this->photo_path);
            } catch (\Throwable $e) {
                // fallback to local url
            }
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Public proxy preview URL that works directly in <img> tags without authorization headers.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        if (empty($this->id) || empty($this->picklist_id) || empty($this->picklist_item_id)) {
            return null;
        }

        $base = config('app.url') ? rtrim(config('app.url'), '/') : '';
        return "{$base}/api/distributor-channel/v1/logistic/picklists/{$this->picklist_id}/items/{$this->picklist_item_id}/label-photos/{$this->id}/preview";
    }

    /**
     * Get the picklist this label photo belongs to.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Get the picklist item this label photo belongs to.
     */
    public function picklistItem(): BelongsTo
    {
        return $this->belongsTo(PicklistItem::class, 'picklist_item_id');
    }

    /**
     * Get the user who uploaded this photo.
     */
    public function creator(): BelongsTo
    {
        $instance = new User();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'created_by', 'id', 'creator');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailboxSetting extends Model
{
    use HasFactory;

    protected $table = 'mailbox_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Get setting value with default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value.
     */
    public static function set(string $key, mixed $value, ?string $description = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'description' => $description]
        );
    }
}

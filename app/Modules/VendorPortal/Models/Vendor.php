<?php

namespace App\Modules\VendorPortal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'pgsql_vendor';
    protected $table = 'vendors';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
    }

    protected $fillable = [
        'vendor_code',
        'vendor_type',
        'company_name',
        'company_email',
        'company_phone',
        'company_npwp',
        'nik',
        'address',
        'village',
        'district',
        'city',
        'regencies',
        'province',
        'postal_code',
        'pic_name',
        'pic_phone',
        'pic_email',
        'terms_agreed',
        'terms_agreed_at',
        'registration_status',
        'legal_approval_status',
        'legal_approved_by',
        'legal_approved_at',
        'legal_notes',
        'sap_vendor_code',
        'expedition_id',
        'distributor_code',
    ];

    protected $casts = [
        'terms_agreed' => 'boolean',
        'terms_agreed_at' => 'datetime',
        'legal_approved_at' => 'datetime',
    ];

    protected $appends = [
        'village_name',
        'district_name',
        'regency_name',
        'regencies_name',
        'city_name',
        'province_name',
        'region_info',
    ];

    public static array $regionCache = [
        'village' => [],
        'district' => [],
        'regency' => [],
        'province' => [],
    ];

    public static function preloadRegionNames($vendors): void
    {
        $items = is_iterable($vendors) ? $vendors : [$vendors];

        $villageIds = [];
        $districtIds = [];
        $regencyIds = [];
        $provinceIds = [];

        foreach ($items as $v) {
            if (!$v) continue;

            if (!empty($v->village) && is_numeric($v->village)) {
                $id = (int)$v->village;
                if (!isset(self::$regionCache['village'][$id])) $villageIds[] = $id;
            }
            if (!empty($v->district) && is_numeric($v->district)) {
                $id = (int)$v->district;
                if (!isset(self::$regionCache['district'][$id])) $districtIds[] = $id;
            }
            $regVal = !empty($v->regencies) ? $v->regencies : (!empty($v->city) ? $v->city : null);
            if (!empty($regVal) && is_numeric($regVal)) {
                $id = (int)$regVal;
                if (!isset(self::$regionCache['regency'][$id])) $regencyIds[] = $id;
            }
            if (!empty($v->province) && is_numeric($v->province)) {
                $id = (int)$v->province;
                if (!isset(self::$regionCache['province'][$id])) $provinceIds[] = $id;
            }
        }

        try {
            if (!empty($villageIds)) {
                $villages = \App\Models\Village::whereIn('id', array_values(array_unique($villageIds)))->pluck('name', 'id');
                foreach ($villages as $id => $name) {
                    self::$regionCache['village'][$id] = $name;
                }
            }
            if (!empty($districtIds)) {
                $districts = \App\Models\District::whereIn('id', array_values(array_unique($districtIds)))->pluck('name', 'id');
                foreach ($districts as $id => $name) {
                    self::$regionCache['district'][$id] = $name;
                }
            }
            if (!empty($regencyIds)) {
                $regencies = \App\Models\Regency::whereIn('id', array_values(array_unique($regencyIds)))->pluck('name', 'id');
                foreach ($regencies as $id => $name) {
                    self::$regionCache['regency'][$id] = $name;
                }
            }
            if (!empty($provinceIds)) {
                $provinces = \App\Models\Province::whereIn('id', array_values(array_unique($provinceIds)))->pluck('name', 'id');
                foreach ($provinces as $id => $name) {
                    self::$regionCache['province'][$id] = $name;
                }
            }
        } catch (\Throwable $e) {
            // Gracefully ignore table / lookup errors
        }
    }

    public function getVillageNameAttribute(): ?string
    {
        if (empty($this->village)) {
            return null;
        }

        if (is_numeric($this->village)) {
            $id = (int) $this->village;
            if (isset(self::$regionCache['village'][$id])) {
                return self::$regionCache['village'][$id];
            }
            try {
                $name = \App\Models\Village::find($id)?->name;
                if ($name) {
                    self::$regionCache['village'][$id] = $name;
                    return $name;
                }
            } catch (\Throwable $e) {}
        }

        return (string) $this->village;
    }

    public function getDistrictNameAttribute(): ?string
    {
        if (empty($this->district)) {
            return null;
        }

        if (is_numeric($this->district)) {
            $id = (int) $this->district;
            if (isset(self::$regionCache['district'][$id])) {
                return self::$regionCache['district'][$id];
            }
            try {
                $name = \App\Models\District::find($id)?->name;
                if ($name) {
                    self::$regionCache['district'][$id] = $name;
                    return $name;
                }
            } catch (\Throwable $e) {}
        }

        return (string) $this->district;
    }

    public function getRegencyNameAttribute(): ?string
    {
        $val = !empty($this->regencies) ? $this->regencies : (!empty($this->city) ? $this->city : null);
        if (empty($val)) {
            return null;
        }

        if (is_numeric($val)) {
            $id = (int) $val;
            if (isset(self::$regionCache['regency'][$id])) {
                return self::$regionCache['regency'][$id];
            }
            try {
                $name = \App\Models\Regency::find($id)?->name;
                if ($name) {
                    self::$regionCache['regency'][$id] = $name;
                    return $name;
                }
            } catch (\Throwable $e) {}
        }

        return (string) $val;
    }

    public function getRegenciesNameAttribute(): ?string
    {
        return $this->regency_name;
    }

    public function getCityNameAttribute(): ?string
    {
        return $this->regency_name;
    }

    public function getProvinceNameAttribute(): ?string
    {
        if (empty($this->province)) {
            return null;
        }

        if (is_numeric($this->province)) {
            $id = (int) $this->province;
            if (isset(self::$regionCache['province'][$id])) {
                return self::$regionCache['province'][$id];
            }
            try {
                $name = \App\Models\Province::find($id)?->name;
                if ($name) {
                    self::$regionCache['province'][$id] = $name;
                    return $name;
                }
            } catch (\Throwable $e) {}
        }

        return (string) $this->province;
    }

    public function getRegionInfoAttribute(): ?array
    {
        $village = $this->village;
        $district = $this->district;
        $regency = !empty($this->regencies) ? $this->regencies : (!empty($this->city) ? $this->city : null);
        $province = $this->province;

        if (empty($village) && empty($district) && empty($regency) && empty($province)) {
            return null;
        }

        return [
            'village' => [
                'id'   => $village,
                'name' => $this->village_name,
            ],
            'district' => [
                'id'   => $district,
                'name' => $this->district_name,
            ],
            'regency' => [
                'id'   => $regency,
                'name' => $this->regency_name,
            ],
            'province' => [
                'id'   => $province,
                'name' => $this->province_name,
            ],
        ];
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class, 'vendor_id');
    }

    public function users()
    {
        return $this->hasMany(VendorUser::class, 'vendor_id');
    }

    public function approvalHistories()
    {
        return $this->hasMany(VendorApprovalHistory::class, 'vendor_id')->orderBy('created_at', 'desc');
    }

    public function legalApprover()
    {
        return $this->belongsTo(User::class, 'legal_approved_by');
    }

    public function dispatchLogs()
    {
        return $this->hasMany(VendorCredentialsDispatchLog::class, 'vendor_id')->orderBy('created_at', 'desc');
    }

    public function expedition()
    {
        return $this->belongsTo(\App\Models\Expedition::class, 'expedition_id');
    }
}

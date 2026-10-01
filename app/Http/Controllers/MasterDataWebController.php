<?php

namespace App\Http\Controllers;

use App\Models\Expedition;
use App\Models\MasterLeadtime;
use App\Models\WarehouseOrigin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterDataWebController extends Controller
{
    /**
     * Supported Master Data Tables registry with schema definitions.
     */
    protected function getRegistry(): array
    {
        // Fetch warehouse origin options for relations
        $warehouseOptions = [];
        try {
            $origins = WarehouseOrigin::orderBy('whs_name_origin')->get();
            foreach ($origins as $o) {
                $warehouseOptions[$o->id] = "{$o->whs_name_origin} ({$o->whs_code})";
            }
        } catch (\Throwable $e) {
            // Fallback if table not yet seeded
        }

        return [
            'master_leadtimes' => [
                'name'        => 'master_leadtimes',
                'label'       => 'Master Leadtime',
                'badge'       => 'Ekspedisi',
                'description' => 'Benchmark estimasi durasi pengiriman muatan dari gudang asal ke kota / alamat tujuan',
                'model'       => MasterLeadtime::class,
                'order_by'    => 'id',
                'order_dir'   => 'desc',
                'fields'      => [
                    'warehouse_origin_id' => [
                        'label'         => 'Gudang Asal (Relasi Origin)',
                        'type'          => 'select',
                        'options'       => ['' => '-- Pilih Gudang Asal --'] + $warehouseOptions,
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'origin_warehouse_code' => [
                        'label'         => 'Kode Gudang Asal',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: WHS-SUB / WHS01',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'origin_warehouse_name' => [
                        'label'         => 'Nama Gudang Asal (Muatan)',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Gudang Utama Surabaya Waru',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'origin_city' => [
                        'label'         => 'Kota Asal',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Surabaya / Sidoarjo',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'destination_name' => [
                        'label'         => 'Nama Lokasi / Area Tujuan',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Gudang Distribusi Bandung / Retail DC',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'destination_city' => [
                        'label'         => 'Kota / Kabupaten Tujuan',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Bandung Kota / Kab. Bandung',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'destination_province' => [
                        'label'         => 'Provinsi Tujuan',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Jawa Barat',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'avg_lead_time_days' => [
                        'label'         => 'Rata-rata Lead Time (Hari)',
                        'type'          => 'number',
                        'step'          => '0.01',
                        'min'           => '0',
                        'placeholder'   => 'Contoh: 2.50',
                        'required'      => true,
                        'table_visible' => true,
                        'badge_style'   => 'blue',
                    ],
                    'min_lead_time_days' => [
                        'label'         => 'Min Lead Time (Hari)',
                        'type'          => 'number',
                        'step'          => '0.01',
                        'min'           => '0',
                        'placeholder'   => 'Contoh: 2.00',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'max_lead_time_days' => [
                        'label'         => 'Max Lead Time (Hari)',
                        'type'          => 'number',
                        'step'          => '0.01',
                        'min'           => '0',
                        'placeholder'   => 'Contoh: 3.00',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'lead_time_unit' => [
                        'label'         => 'Satuan Lead Time',
                        'type'          => 'select',
                        'options'       => ['DAYS' => 'DAYS (Hari)', 'HOURS' => 'HOURS (Jam)'],
                        'default'       => 'DAYS',
                        'required'      => true,
                        'table_visible' => false,
                    ],
                    'transport_mode' => [
                        'label'         => 'Moda Transportasi',
                        'type'          => 'select',
                        'options'       => [
                            ''     => '-- Pilih Moda Transportasi --',
                            'LAND' => 'LAND (Darat)',
                            'SEA'  => 'SEA (Laut)',
                            'AIR'  => 'AIR (Udara)',
                        ],
                        'default'       => 'LAND',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'distance_km' => [
                        'label'         => 'Jarak Estimasi (KM)',
                        'type'          => 'number',
                        'step'          => '0.01',
                        'min'           => '0',
                        'placeholder'   => 'Contoh: 680.00',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'status' => [
                        'label'         => 'Status',
                        'type'          => 'select',
                        'options'       => ['ACTIVE' => 'ACTIVE (Aktif)', 'INACTIVE' => 'INACTIVE (Nonaktif)'],
                        'default'       => 'ACTIVE',
                        'required'      => true,
                        'table_visible' => true,
                        'badge_style'   => 'status',
                    ],
                    'remarks' => [
                        'label'         => 'Catatan / Catatan Rute',
                        'type'          => 'textarea',
                        'placeholder'   => 'Catatan rute, kapal feri, toleransi macet...',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                ],
            ],

            'warehouse_origins' => [
                'name'        => 'warehouse_origins',
                'label'       => 'Warehouse Origins',
                'badge'       => 'Origin Point',
                'description' => 'Master data titik lokasi gudang asal muatan untuk penugasan ekspedisi',
                'model'       => WarehouseOrigin::class,
                'order_by'    => 'id',
                'order_dir'   => 'desc',
                'fields'      => [
                    'whs_name_origin' => [
                        'label'         => 'Nama Origin Gudang',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Origin SBY Waru',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'whs_code' => [
                        'label'         => 'Kode Gudang SAP',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: WHS01',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'whs_name' => [
                        'label'         => 'Nama Gudang SAP',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Gudang Utama Surabaya',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'street' => [
                        'label'         => 'Alamat Jalan',
                        'type'          => 'textarea',
                        'placeholder'   => 'Alamat lengkap titik penjemputan/muatan...',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'status' => [
                        'label'         => 'Status',
                        'type'          => 'select',
                        'options'       => ['ACTIVE' => 'ACTIVE (Aktif)', 'INACTIVE' => 'INACTIVE (Nonaktif)'],
                        'default'       => 'ACTIVE',
                        'required'      => true,
                        'table_visible' => true,
                        'badge_style'   => 'status',
                    ],
                ],
            ],

            'expeditions' => [
                'name'        => 'expeditions',
                'label'       => 'Master Expeditions',
                'badge'       => 'Transporter',
                'description' => 'Daftar vendor ekspedisi / transporter pengiriman resmi yang terdaftar',
                'model'       => Expedition::class,
                'order_by'    => 'id',
                'order_dir'   => 'desc',
                'fields'      => [
                    'expedition_code' => [
                        'label'         => 'Kode Ekspedisi',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: EXP-001 / DAKOTA',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'expedition_name' => [
                        'label'         => 'Nama Ekspedisi',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: PT Dakota Buana Semesta',
                        'required'      => true,
                        'table_visible' => true,
                    ],
                    'pic_name' => [
                        'label'         => 'Nama PIC / Kontak',
                        'type'          => 'text',
                        'placeholder'   => 'Nama perwakilan ekspedisi',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'pic_phone' => [
                        'label'         => 'No. Telepon / WhatsApp PIC',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: 08123456789',
                        'required'      => false,
                        'table_visible' => true,
                    ],
                    'city' => [
                        'label'         => 'Kota Domisili',
                        'type'          => 'text',
                        'placeholder'   => 'Contoh: Surabaya',
                        'required'      => false,
                        'table_visible' => false,
                    ],
                    'status' => [
                        'label'         => 'Status',
                        'type'          => 'select',
                        'options'       => ['ACTIVE' => 'ACTIVE (Aktif)', 'INACTIVE' => 'INACTIVE (Nonaktif)'],
                        'default'       => 'ACTIVE',
                        'required'      => true,
                        'table_visible' => true,
                        'badge_style'   => 'status',
                    ],
                ],
            ],
        ];
    }

    /**
     * Display the Dynamic Master Data CRUD dashboard.
     */
    public function index(Request $request)
    {
        $registry = $this->getRegistry();
        $selectedTable = $request->query('table', 'master_leadtimes');

        if (!array_key_exists($selectedTable, $registry)) {
            $selectedTable = 'master_leadtimes';
        }

        $tableConfig = $registry[$selectedTable];
        $modelClass = $tableConfig['model'];

        $query = $modelClass::query();

        // Search filtering
        $search = trim((string)$request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($tableConfig, $search) {
                foreach ($tableConfig['fields'] as $col => $f) {
                    if (in_array($f['type'], ['text', 'textarea'])) {
                        $q->orWhere($col, 'like', "%{$search}%");
                    }
                }
            });
        }

        $orderBy = $tableConfig['order_by'] ?? 'id';
        $orderDir = $tableConfig['order_dir'] ?? 'desc';
        $rows = $query->orderBy($orderBy, $orderDir)->paginate(15)->withQueryString();

        // Count totals for each table for badge counters
        $counts = [];
        foreach ($registry as $tblKey => $cfg) {
            try {
                $counts[$tblKey] = ($cfg['model'])::count();
            } catch (\Throwable $e) {
                $counts[$tblKey] = 0;
            }
        }

        return view('master-data-dashboard', compact(
            'registry',
            'selectedTable',
            'tableConfig',
            'rows',
            'search',
            'counts'
        ));
    }

    /**
     * Store a newly created record dynamically.
     */
    public function store(Request $request, string $table)
    {
        $registry = $this->getRegistry();
        if (!array_key_exists($table, $registry)) {
            return redirect('/monitoringsm/master-data')->with('error', 'Tabel master data tidak dikenal.');
        }

        $config = $registry[$table];
        $rules = $this->buildValidationRules($config['fields']);
        $validated = $request->validate($rules);

        $payload = $this->cleanPayload($config['fields'], $validated);

        if (in_array('created_by', (new $config['model'])->getFillable())) {
            $payload['created_by'] = auth()->id() ?? null;
        }

        ($config['model'])::create($payload);

        return redirect("/monitoringsm/master-data?table={$table}")
            ->with('success', "Data {$config['label']} berhasil ditambahkan.");
    }

    /**
     * Update an existing record dynamically.
     */
    public function update(Request $request, string $table, $id)
    {
        $registry = $this->getRegistry();
        if (!array_key_exists($table, $registry)) {
            return redirect('/monitoringsm/master-data')->with('error', 'Tabel master data tidak dikenal.');
        }

        $config = $registry[$table];
        $record = ($config['model'])::findOrFail($id);

        $rules = $this->buildValidationRules($config['fields'], $id);
        $validated = $request->validate($rules);

        $payload = $this->cleanPayload($config['fields'], $validated);

        if (in_array('updated_by', $record->getFillable())) {
            $payload['updated_by'] = auth()->id() ?? null;
        }

        $record->update($payload);

        return redirect("/monitoringsm/master-data?table={$table}")
            ->with('success', "Data {$config['label']} berhasil diperbarui.");
    }

    /**
     * Delete an existing record dynamically.
     */
    public function destroy(Request $request, string $table, $id)
    {
        $registry = $this->getRegistry();
        if (!array_key_exists($table, $registry)) {
            return redirect('/monitoringsm/master-data')->with('error', 'Tabel master data tidak dikenal.');
        }

        $config = $registry[$table];
        $record = ($config['model'])::findOrFail($id);
        $record->delete();

        return redirect("/monitoringsm/master-data?table={$table}")
            ->with('success', "Data {$config['label']} (ID: {$id}) berhasil dihapus.");
    }

    /**
     * Build dynamic validation rules from field config.
     */
    protected function buildValidationRules(array $fields, $ignoreId = null): array
    {
        $rules = [];
        foreach ($fields as $col => $f) {
            $colRules = [];
            $colRules[] = !empty($f['required']) ? 'required' : 'nullable';

            switch ($f['type']) {
                case 'number':
                    $colRules[] = 'numeric';
                    if (isset($f['min'])) {
                        $colRules[] = 'min:' . $f['min'];
                    }
                    break;
                case 'select':
                    $options = array_keys($f['options'] ?? []);
                    if (!empty($options)) {
                        $colRules[] = 'in:' . implode(',', array_map('strval', $options));
                    }
                    break;
                case 'text':
                    $colRules[] = 'string';
                    $colRules[] = 'max:255';
                    break;
                case 'textarea':
                    $colRules[] = 'string';
                    break;
            }

            $rules[$col] = $colRules;
        }
        return $rules;
    }

    /**
     * Clean and prepare validated payload for model saving.
     */
    protected function cleanPayload(array $fields, array $validated): array
    {
        $payload = [];
        foreach ($fields as $col => $f) {
            if (array_key_exists($col, $validated)) {
                $val = $validated[$col];
                if ($f['type'] === 'number' && ($val === '' || $val === null)) {
                    $val = null;
                }
                $payload[$col] = $val;
            }
        }
        return $payload;
    }
}

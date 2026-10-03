<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use App\Models\DistributorApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyWebController extends Controller
{
    /**
     * Display the API Key Monitoring & Management Dashboard.
     */
    public function index()
    {
        $apiKeys = DistributorApiKey::with('distributors')
            ->orderBy('created_at', 'desc')
            ->get();

        $distributors = Distributor::orderBy('name', 'asc')->get();

        $stats = [
            'total_keys'         => $apiKeys->count(),
            'active_keys'        => $apiKeys->where('is_active', true)->count(),
            'inactive_keys'      => $apiKeys->where('is_active', false)->count(),
            'total_distributors' => $distributors->count(),
        ];

        return view('api-keys-dashboard', compact('apiKeys', 'distributors', 'stats'));
    }

    /**
     * Generate a new API Key (supports multiple distributors).
     */
    public function store(Request $request)
    {
        $request->validate([
            'distributor_ids'  => 'required|array|min:1',
            'distributor_ids.*' => 'exists:distributors,id',
            'name'             => 'required|string|max:150',
            'company_name'     => 'nullable|string|max:200',
            'allowed_ips'      => 'nullable|string',
        ], [
            'distributor_ids.required' => 'Silakan pilih minimal 1 Distributor.',
            'distributor_ids.min'      => 'Silakan pilih minimal 1 Distributor.',
            'name.required'            => 'Label/Nama sistem integrasi wajib diisi.',
        ]);

        $rawKey    = 'susanti_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($rawKey);
        $keyPrefix = substr($rawKey, 0, 20);

        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $allowedIps = array_filter(array_map('trim', explode(',', $request->input('allowed_ips'))));
        }

        $distributorIds = $request->input('distributor_ids');

        // Create the API Key record (no single distributor_id binding)
        $apiKeyRecord = DistributorApiKey::create([
            'distributor_id' => null,
            'name'           => $request->input('name'),
            'company_name'   => $request->input('company_name'),
            'key_prefix'     => $keyPrefix,
            'api_key_hash'   => $hashedKey,
            'allowed_ips'    => $allowedIps,
            'is_active'      => true,
        ]);

        // Attach all selected distributors via pivot
        $apiKeyRecord->distributors()->attach($distributorIds);

        $distributorNames = Distributor::whereIn('id', $distributorIds)->pluck('name')->implode(', ');

        return redirect('/monitoringsm/api-keys')
            ->with('success', "API Key untuk [{$distributorNames}] berhasil di-generate!")
            ->with('generated_key', $rawKey)
            ->with('distributor_name', $distributorNames);
    }

    /**
     * Toggle active/inactive status of an API Key.
     */
    public function toggleStatus(int $id)
    {
        $apiKey = DistributorApiKey::with('distributors')->findOrFail($id);
        $apiKey->is_active = !$apiKey->is_active;
        $apiKey->save();

        $statusText = $apiKey->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $name       = $apiKey->name;

        return redirect('/monitoringsm/api-keys')->with('info', "API Key '{$name}' berhasil {$statusText}.");
    }

    /**
     * Revoke / Delete an API Key permanently.
     */
    public function destroy(int $id)
    {
        $apiKey = DistributorApiKey::findOrFail($id);
        $name   = $apiKey->name;

        $apiKey->distributors()->detach(); // remove pivot records
        $apiKey->delete();

        return redirect('/monitoringsm/api-keys')->with('success', "API Key '{$name}' berhasil dicabut (revoked) & dihapus.");
    }
}

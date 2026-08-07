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
        $apiKeys = DistributorApiKey::with('distributor')
            ->orderBy('created_at', 'desc')
            ->get();

        $distributors = Distributor::orderBy('name', 'asc')->get();

        $stats = [
            'total_keys' => $apiKeys->count(),
            'active_keys' => $apiKeys->where('is_active', true)->count(),
            'inactive_keys' => $apiKeys->where('is_active', false)->count(),
            'total_distributors' => $distributors->count(),
        ];

        return view('api-keys-dashboard', compact('apiKeys', 'distributors', 'stats'));
    }

    /**
     * Generate a new API Key for a Distributor.
     */
    public function store(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'name' => 'required|string|max:150',
            'allowed_ips' => 'nullable|string',
        ], [
            'distributor_id.required' => 'Silakan pilih Distributor.',
            'distributor_id.exists' => 'Distributor yang dipilih tidak valid.',
            'name.required' => 'Label/Nama sistem integrasi wajib diisi.',
        ]);

        $rawKey = 'susanti_sec_' . Str::random(40);
        $hashedKey = DistributorApiKey::hashKey($rawKey);
        $keyPrefix = substr($rawKey, 0, 15);

        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $allowedIps = array_filter(array_map('trim', explode(',', $request->input('allowed_ips'))));
        }

        $distributor = Distributor::findOrFail($request->input('distributor_id'));

        DistributorApiKey::create([
            'distributor_id' => $distributor->id,
            'name' => $request->input('name'),
            'key_prefix' => $keyPrefix,
            'api_key_hash' => $hashedKey,
            'allowed_ips' => $allowedIps,
            'is_active' => true,
        ]);

        return redirect('/monitoringsm/api-keys')
            ->with('success', "API Key untuk '{$distributor->name}' berhasil di-generate!")
            ->with('generated_key', $rawKey)
            ->with('distributor_name', $distributor->name);
    }

    /**
     * Toggle active/inactive status of an API Key.
     */
    public function toggleStatus(int $id)
    {
        $apiKey = DistributorApiKey::with('distributor')->findOrFail($id);
        $apiKey->is_active = !$apiKey->is_active;
        $apiKey->save();

        $statusText = $apiKey->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect('/monitoringsm/api-keys')->with('info', "API Key '{$apiKey->name}' ({$apiKey->distributor?->name}) berhasil {$statusText}.");
    }

    /**
     * Revoke / Delete an API Key permanently.
     */
    public function destroy(int $id)
    {
        $apiKey = DistributorApiKey::with('distributor')->findOrFail($id);
        $name = $apiKey->name;
        $distributorName = $apiKey->distributor?->name;

        $apiKey->delete();

        return redirect('/monitoringsm/api-keys')->with('success', "API Key '{$name}' ({$distributorName}) berhasil dicabut (revoked) & dihapus.");
    }
}

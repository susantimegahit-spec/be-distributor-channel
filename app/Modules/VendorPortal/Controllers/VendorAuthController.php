<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Requests\VendorLoginRequest;
use App\Modules\VendorPortal\Services\VendorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAuthController extends Controller
{
    protected VendorAuthService $authService;

    public function __construct(VendorAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Endpoint login vendor portal.
     */
    public function login(VendorLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->input('vendor_type'),
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Vendor login successful.',
            'data' => $result,
        ]);
    }

    /**
     * Profil vendor yang sedang login beserta detail berkas dokumen legalitas.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated access.',
            ], 401);
        }

        $vendor = $user->vendor;
        $documents = [];

        if ($vendor) {
            $vendor->load(['documents', 'expedition']);
            $documents = $vendor->documents->map(function ($doc) {
                return [
                    'id'                  => $doc->id,
                    'document_type'       => $doc->document_type,
                    'document_number'     => $doc->document_number,
                    'file_name'           => $doc->file_name,
                    'file_size'           => $doc->file_size,
                    'file_mime'           => $doc->file_mime,
                    'file_url'            => $doc->file_url,
                    'verification_status' => $doc->verification_status,
                    'notes'               => $doc->notes,
                    'verification_notes'  => $doc->verification_notes ?? $doc->notes,
                    'verified_at'         => $doc->verified_at ? $doc->verified_at->toIso8601String() : null,
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendor profile and documents retrieved successfully.',
            'data' => [
                'user' => [
                    'id'                   => $user->id,
                    'name'                 => $user->name,
                    'email'                => $user->email,
                    'role'                 => $user->role,
                    'must_change_password' => (bool) $user->must_change_password,
                ],
                'vendor'          => $vendor,
                'sap_vendor_code' => $vendor?->sap_vendor_code,
                'documents'       => $documents,
            ],
        ]);
    }

    /**
     * Ganti kata sandi akun vendor.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated access.',
            ], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ], [
            'current_password.required'          => 'Current password is required.',
            'new_password.required'              => 'New password is required.',
            'new_password.min'                   => 'New password must be at least 8 characters.',
            'new_password.confirmed'             => 'New password confirmation does not match.',
            'new_password_confirmation.required' => 'Password confirmation is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->authService->changePassword(
                $user,
                $request->input('current_password'),
                $request->input('new_password')
            );

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
                'data'    => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        }
    }

    /**
     * Logout vendor.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out from vendor portal.',
        ]);
    }
}

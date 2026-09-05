<?php

namespace App\Modules\DocumentApproval\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTypeController extends Controller
{
    /**
     * Get list of Document Types with search & filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DocumentType::with(['activeSchema'])->orderBy('name', 'asc');

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        // Return all if all=true or per_page
        if ($request->boolean('all')) {
            $types = $query->get();
            return response()->json([
                'success' => true,
                'message' => 'Document types retrieved successfully',
                'data' => $types,
            ]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $types = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Document types retrieved successfully',
            'data' => $types->items(),
            'pagination' => [
                'current_page' => $types->currentPage(),
                'last_page' => $types->lastPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
            ]
        ]);
    }

    /**
     * Get single Document Type by ID or Code.
     */
    public function show(string|int $idOrCode): JsonResponse
    {
        $query = DocumentType::with(['schemas.fields', 'workflows.stages']);

        if (is_numeric($idOrCode)) {
            $type = $query->find($idOrCode);
        } else {
            $type = $query->where('code', strtoupper($idOrCode))->first();
        }

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => "Document type '{$idOrCode}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document type detail retrieved',
            'data' => $type,
        ]);
    }

    /**
     * Store a newly created Document Type with optional file attachments.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:document_types,code',
            'name' => 'required|string|max:255',
            'sap_object_type' => 'nullable|integer',
            'module' => 'nullable|string|max:50',
            'header_source' => 'nullable|string|max:50',
            'line_source' => 'nullable|string|max:50',
            'adapter_class' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'icon' => 'nullable|file|mimes:jpeg,png,jpg,svg,webp|max:2048',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,zip|max:10240',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);

        // 1. Handle Icon Upload
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconPath = $iconFile->store('document_types/icons', 'public');
            $validated['icon_path'] = $iconPath;
        }

        // 2. Handle Document Attachment / SOP Template Upload
        if ($request->hasFile('attachment')) {
            $attachFile = $request->file('attachment');
            $originalName = $attachFile->getClientOriginalName();
            $attachPath = $attachFile->store('document_types/attachments', 'public');
            $validated['attachment_path'] = $attachPath;
            $validated['attachment_name'] = $originalName;
        }

        $documentType = DocumentType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document type created successfully',
            'data' => $documentType,
        ], 201);
    }

    /**
     * Update an existing Document Type with optional file replacement.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $documentType = DocumentType::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:document_types,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'sap_object_type' => 'nullable|integer',
            'module' => 'nullable|string|max:50',
            'header_source' => 'nullable|string|max:50',
            'line_source' => 'nullable|string|max:50',
            'adapter_class' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'icon' => 'nullable|file|mimes:jpeg,png,jpg,svg,webp|max:2048',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,zip|max:10240',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        // Handle Icon Upload & Replace
        if ($request->hasFile('icon')) {
            if ($documentType->icon_path && Storage::disk('public')->exists($documentType->icon_path)) {
                Storage::disk('public')->delete($documentType->icon_path);
            }
            $validated['icon_path'] = $request->file('icon')->store('document_types/icons', 'public');
        }

        // Handle Attachment Upload & Replace
        if ($request->hasFile('attachment')) {
            if ($documentType->attachment_path && Storage::disk('public')->exists($documentType->attachment_path)) {
                Storage::disk('public')->delete($documentType->attachment_path);
            }
            $attachFile = $request->file('attachment');
            $validated['attachment_path'] = $attachFile->store('document_types/attachments', 'public');
            $validated['attachment_name'] = $attachFile->getClientOriginalName();
        }

        $documentType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document type updated successfully',
            'data' => $documentType,
        ]);
    }

    /**
     * Delete a Document Type.
     */
    public function destroy(int $id): JsonResponse
    {
        $documentType = DocumentType::withCount('approvals')->findOrFail($id);

        if ($documentType->approvals_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete document type '{$documentType->name}' because it has {$documentType->approvals_count} existing approval transactions. Consider setting it inactive instead.",
            ], 422);
        }

        // Delete uploaded files
        if ($documentType->icon_path && Storage::disk('public')->exists($documentType->icon_path)) {
            Storage::disk('public')->delete($documentType->icon_path);
        }
        if ($documentType->attachment_path && Storage::disk('public')->exists($documentType->attachment_path)) {
            Storage::disk('public')->delete($documentType->attachment_path);
        }

        $documentType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document type deleted successfully',
        ]);
    }

    /**
     * Toggle Document Type active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $documentType = DocumentType::findOrFail($id);
        $documentType->is_active = !$documentType->is_active;
        $documentType->save();

        return response()->json([
            'success' => true,
            'message' => 'Document type status updated',
            'data' => [
                'id' => $documentType->id,
                'is_active' => $documentType->is_active,
            ]
        ]);
    }

    /**
     * Dedicated file upload endpoint for Document Type.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'attachment' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,zip,png,jpg,jpeg|max:15360',
        ]);

        $documentType = DocumentType::findOrFail($id);

        if ($documentType->attachment_path && Storage::disk('public')->exists($documentType->attachment_path)) {
            Storage::disk('public')->delete($documentType->attachment_path);
        }

        $file = $request->file('attachment');
        $path = $file->store('document_types/attachments', 'public');

        $documentType->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded successfully',
            'data' => [
                'attachment_name' => $documentType->attachment_name,
                'attachment_path' => $documentType->attachment_path,
                'attachment_url' => $documentType->attachment_url,
            ]
        ]);
    }
}

<?php

namespace App\Modules\TaskManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Services\HierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class HierarchyController extends Controller
{
    protected HierarchyService $hierarchyService;

    public function __construct(HierarchyService $hierarchyService)
    {
        $this->hierarchyService = $hierarchyService;
    }

    public function getWorkspaces(): JsonResponse
    {
        $workspaces = $this->hierarchyService->getWorkspaces();
        return response()->json([
            'status'  => 'success',
            'message' => 'Workspaces retrieved successfully',
            'data'    => $workspaces,
        ]);
    }

    public function getWorkspace(int $id): JsonResponse
    {
        $workspace = $this->hierarchyService->getWorkspaceDetail($id);
        if (!$workspace) {
            return response()->json(['status' => 'error', 'message' => 'Workspace not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $workspace]);
    }

    public function createWorkspace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workspace_code' => 'required|string|max:50|unique:pgsql_corporate.tm_workspaces,workspace_code',
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string',
            'logo_url'       => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user() ? $request->user()->id : 1;
        $workspace = $this->hierarchyService->createWorkspace($validator->validated(), $userId);
        return response()->json(['status' => 'success', 'data' => $workspace], 201);
    }

    public function getSpaces(Request $request): JsonResponse
    {
        $workspaceId = (int)$request->query('workspace_id', 1);
        $spaces = $this->hierarchyService->getSpaces($workspaceId);
        return response()->json(['status' => 'success', 'data' => $spaces]);
    }

    public function getSpace(int $id): JsonResponse
    {
        $space = $this->hierarchyService->getSpaceDetail($id);
        if (!$space) {
            return response()->json(['status' => 'error', 'message' => 'Space not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $space]);
    }

    public function createSpace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workspace_id'   => 'required|integer',
            'department_id'  => 'nullable|integer',
            'space_name'     => 'required|string|max:150',
            'color_hex'      => 'nullable|string|max:10',
            'icon_name'      => 'nullable|string|max:50',
            'description'    => 'nullable|string',
            'is_private'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user() ? $request->user()->id : 1;
        $space = $this->hierarchyService->createSpace($validator->validated(), $userId);
        return response()->json(['status' => 'success', 'data' => $space], 201);
    }

    public function getFolders(Request $request): JsonResponse
    {
        $spaceId = (int)$request->query('space_id');
        if (!$spaceId) {
            return response()->json(['status' => 'error', 'message' => 'space_id query parameter is required'], 422);
        }
        $folders = $this->hierarchyService->getFolders($spaceId);
        return response()->json(['status' => 'success', 'data' => $folders]);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'space_id'    => 'required|integer',
            'folder_name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'color_hex'   => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user() ? $request->user()->id : 1;
        $folder = $this->hierarchyService->createFolder($validator->validated(), $userId);
        return response()->json(['status' => 'success', 'data' => $folder], 201);
    }

    public function getLists(Request $request): JsonResponse
    {
        $spaceId = (int)$request->query('space_id');
        if (!$spaceId) {
            return response()->json(['status' => 'error', 'message' => 'space_id query parameter is required'], 422);
        }
        $folderId = $request->query('folder_id') !== null ? (int)$request->query('folder_id') : null;
        $lists = $this->hierarchyService->getLists($spaceId, $folderId);
        return response()->json(['status' => 'success', 'data' => $lists]);
    }

    public function createList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'space_id'     => 'required|integer',
            'folder_id'    => 'nullable|integer',
            'list_name'    => 'required|string|max:150',
            'description'  => 'nullable|string',
            'color_hex'    => 'nullable|string|max:10',
            'default_view' => 'nullable|string|in:LIST,BOARD,CALENDAR,GANTT',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user() ? $request->user()->id : 1;
        $list = $this->hierarchyService->createList($validator->validated(), $userId);
        return response()->json(['status' => 'success', 'data' => $list], 201);
    }
}

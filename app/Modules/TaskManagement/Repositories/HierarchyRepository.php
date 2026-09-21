<?php

namespace App\Modules\TaskManagement\Repositories;

use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use Illuminate\Database\Eloquent\Collection;

class HierarchyRepository implements HierarchyRepositoryInterface
{
    public function getWorkspaces(): Collection
    {
        return TmWorkspace::where('is_active', true)->with('spaces')->get();
    }

    public function findWorkspace(int $id): ?TmWorkspace
    {
        return TmWorkspace::with('spaces.folders.lists')->find($id);
    }

    public function createWorkspace(array $data): TmWorkspace
    {
        return TmWorkspace::create($data);
    }

    public function getSpaces(int $workspaceId): Collection
    {
        return TmSpace::with(['department:id,dept_code,dept_name', 'folders.lists', 'lists' => function ($q) {
            $q->whereNull('folder_id');
        }])
        ->where('workspace_id', $workspaceId)
        ->get();
    }

    public function findSpace(int $id): ?TmSpace
    {
        return TmSpace::with(['department', 'members.employee', 'folders.lists', 'lists' => function ($q) {
            $q->whereNull('folder_id');
        }])->find($id);
    }

    public function createSpace(array $data): TmSpace
    {
        return TmSpace::create($data);
    }

    public function updateSpace(TmSpace $space, array $data): bool
    {
        return $space->update($data);
    }

    public function getFolders(int $spaceId): Collection
    {
        return TmFolder::where('space_id', $spaceId)
            ->where('is_archived', false)
            ->with('lists')
            ->orderBy('sort_order')
            ->get();
    }

    public function findFolder(int $id): ?TmFolder
    {
        return TmFolder::with('lists')->find($id);
    }

    public function createFolder(array $data): TmFolder
    {
        return TmFolder::create($data);
    }

    public function updateFolder(TmFolder $folder, array $data): bool
    {
        return $folder->update($data);
    }

    public function getLists(int $spaceId, ?int $folderId = null): Collection
    {
        $query = TmList::where('space_id', $spaceId)->where('is_archived', false);
        if ($folderId !== null) {
            $query->where('folder_id', $folderId);
        }
        return $query->orderBy('sort_order')->get();
    }

    public function findList(int $id): ?TmList
    {
        return TmList::find($id);
    }

    public function createList(array $data): TmList
    {
        return TmList::create($data);
    }

    public function updateList(TmList $list, array $data): bool
    {
        return $list->update($data);
    }
}

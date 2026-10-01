<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use App\Models\TmTask;
use App\Modules\TaskManagement\Repositories\HierarchyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HierarchyService
{
    protected HierarchyRepositoryInterface $hierarchyRepo;

    public function __construct(HierarchyRepositoryInterface $hierarchyRepo)
    {
        $this->hierarchyRepo = $hierarchyRepo;
    }

    public function getWorkspaces(): Collection
    {
        return $this->hierarchyRepo->getWorkspaces();
    }

    public function getWorkspaceDetail(int $id): ?TmWorkspace
    {
        return $this->hierarchyRepo->findWorkspace($id);
    }

    public function createWorkspace(array $data, int $userId): TmWorkspace
    {
        $data['owner_user_id'] = $userId;
        return $this->hierarchyRepo->createWorkspace($data);
    }

    public function getSpaces(int $workspaceId): Collection
    {
        return $this->hierarchyRepo->getSpaces($workspaceId);
    }

    public function getSpaceDetail(string|int $id): ?TmSpace
    {
        return $this->hierarchyRepo->findSpace($id);
    }

    public function createSpace(array $data, int $userId): TmSpace
    {
        if (empty($data['id'])) {
            $data['id'] = !empty($data['department_id']) ? $data['department_id'] : (Str::slug($data['space_name']) . '-' . rand(100, 999));
        }
        if (empty($data['space_slug'])) {
            $data['space_slug'] = Str::slug($data['space_name']) . '-' . rand(100, 999);
        }
        $data['created_by_user_id'] = $userId;
        return $this->hierarchyRepo->createSpace($data);
    }

    public function updateSpace(string|int $id, array $data): TmSpace
    {
        $space = $this->hierarchyRepo->findSpace($id);
        $this->hierarchyRepo->updateSpace($space, $data);
        return $this->hierarchyRepo->findSpace($id);
    }

    public function getFolders(string|int $spaceId): Collection
    {
        return $this->hierarchyRepo->getFolders($spaceId);
    }

    public function createFolder(array $data, int $userId): TmFolder
    {
        $data['created_by_user_id'] = $userId;
        return $this->hierarchyRepo->createFolder($data);
    }

    public function updateFolder(int $id, array $data): TmFolder
    {
        $folder = $this->hierarchyRepo->findFolder($id);
        $this->hierarchyRepo->updateFolder($folder, $data);
        return $this->hierarchyRepo->findFolder($id);
    }

    public function deleteFolder(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $folder = $this->hierarchyRepo->findFolder($id);
            if (!$folder) {
                return false;
            }

            // 1. Dapatkan semua ID list di dalam folder ini
            $listIds = TmList::where('folder_id', $id)->pluck('id')->toArray();

            // 2. Ambil seluruh task yang berada di folder ini atau di list dalam folder ini
            $tasksQuery = TmTask::withTrashed()->where('folder_id', $id);
            if (!empty($listIds)) {
                $tasksQuery->orWhereIn('list_id', $listIds);
            }
            $tasks = $tasksQuery->get();

            // 3. Hapus seluruh task beserta cascade turunannya (subtasks, assignees, checklists, dll)
            foreach ($tasks as $task) {
                $task->forceDelete();
            }

            // 4. Hapus seluruh list di bawah folder ini
            if (!empty($listIds)) {
                TmList::whereIn('id', $listIds)->delete();
            }

            // 5. Hapus folder
            return $this->hierarchyRepo->deleteFolder($folder);
        });
    }

    public function getLists(string|int $spaceId, ?int $folderId = null): Collection
    {
        return $this->hierarchyRepo->getLists($spaceId, $folderId);
    }

    public function createList(array $data, int $userId): TmList
    {
        $data['created_by_user_id'] = $userId;
        return $this->hierarchyRepo->createList($data);
    }

    public function updateList(int $id, array $data): TmList
    {
        $list = $this->hierarchyRepo->findList($id);
        $this->hierarchyRepo->updateList($list, $data);
        return $this->hierarchyRepo->findList($id);
    }
}

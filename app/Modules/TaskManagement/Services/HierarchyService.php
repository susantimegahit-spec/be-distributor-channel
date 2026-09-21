<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use App\Modules\TaskManagement\Repositories\HierarchyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
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

    public function getSpaceDetail(int $id): ?TmSpace
    {
        return $this->hierarchyRepo->findSpace($id);
    }

    public function createSpace(array $data, int $userId): TmSpace
    {
        if (empty($data['space_slug'])) {
            $data['space_slug'] = Str::slug($data['space_name']) . '-' . rand(100, 999);
        }
        $data['created_by_user_id'] = $userId;
        return $this->hierarchyRepo->createSpace($data);
    }

    public function updateSpace(int $id, array $data): TmSpace
    {
        $space = $this->hierarchyRepo->findSpace($id);
        $this->hierarchyRepo->updateSpace($space, $data);
        return $this->hierarchyRepo->findSpace($id);
    }

    public function getFolders(int $spaceId): Collection
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

    public function getLists(int $spaceId, ?int $folderId = null): Collection
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

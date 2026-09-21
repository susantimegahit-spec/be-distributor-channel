<?php

namespace App\Modules\TaskManagement\Repositories;

use App\Models\TmWorkspace;
use App\Models\TmSpace;
use App\Models\TmFolder;
use App\Models\TmList;
use Illuminate\Database\Eloquent\Collection;

interface HierarchyRepositoryInterface
{
    public function getWorkspaces(): Collection;
    public function findWorkspace(int $id): ?TmWorkspace;
    public function createWorkspace(array $data): TmWorkspace;

    public function getSpaces(int $workspaceId): Collection;
    public function findSpace(int $id): ?TmSpace;
    public function createSpace(array $data): TmSpace;
    public function updateSpace(TmSpace $space, array $data): bool;

    public function getFolders(int $spaceId): Collection;
    public function findFolder(int $id): ?TmFolder;
    public function createFolder(array $data): TmFolder;
    public function updateFolder(TmFolder $folder, array $data): bool;

    public function getLists(int $spaceId, ?int $folderId = null): Collection;
    public function findList(int $id): ?TmList;
    public function createList(array $data): TmList;
    public function updateList(TmList $list, array $data): bool;
}

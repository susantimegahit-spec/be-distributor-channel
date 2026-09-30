<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\UserCrudRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserCrudService
{
    protected UserCrudRepositoryInterface $userRepository;

    /**
     * UserCrudService constructor.
     *
     * @param UserCrudRepositoryInterface $userRepository
     */
    public function __construct(UserCrudRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get all users.
     *
     * @return Collection
     */
    public function getAllUsers(): Collection
    {
        return $this->userRepository->all();
    }

    /**
     * Get a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Normalize custom permissions input.
     * Stored raw directly into custom_permissions column as requested by FE.
     *
     * @param mixed $input
     * @return mixed
     */
    public function normalizeCustomPermissions(mixed $input): mixed
    {
        if ($input === null) {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            return null;
        }

        return $input;
    }

    /**
     * Create a new user with optional custom permissions.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make('password123');
        }

        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems'], $data['id_distributor']);

        // Handle actions, custom_permissions or permissions alias
        if (isset($data['actions']) || isset($data['custom_permissions']) || isset($data['permissions'])) {
            $rawPerms = $data['actions'] ?? $data['custom_permissions'] ?? $data['permissions'];
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
        }
        unset($data['actions'], $data['permissions']);

        $orgInput = $data['organization_assignment'] ?? $data['organizational_assignment'] ?? $data['distribution_rule'] ?? $data['distribution_rules'] ?? null;
        if ($orgInput === null) {
            $hasOrgKeys = false;
            $tempOrg = [];
            foreach (['warehouses', 'warehouse', 'branches', 'branch', 'business_units', 'business_unit', 'departments', 'department', 'expeditions', 'expedition', 'distributors', 'distributor'] as $k) {
                if (array_key_exists($k, $data)) {
                    $tempOrg[$k] = $data[$k];
                    $hasOrgKeys = true;
                }
            }
            if ($hasOrgKeys) {
                $orgInput = $tempOrg;
            }
        }
        unset(
            $data['organization_assignment'],
            $data['organizational_assignment'],
            $data['distribution_rule'],
            $data['distribution_rules'],
            $data['warehouses'],
            $data['branches'],
            $data['business_units'],
            $data['departments'],
            $data['expeditions'],
            $data['distributors']
        );

        if (isset($data['unit']) && !isset($data['units'])) {
            $data['units'] = $data['unit'];
        }
        unset($data['unit']);

        $isProductionUser = !empty($data['whs_code']) ||
            !empty($data['units']) ||
            !empty($data['ocr_code']) ||
            !empty($data['ocr_code2']) ||
            !empty($data['ocr_code3']);

        if ($isProductionUser && empty($data['production_code'])) {
            $data['production_code'] = User::generateProductionCode();
        }

        $user = $this->userRepository->create($data);

        if ($orgInput !== null) {
            $this->syncOrganizationAssignments($user, $orgInput);
        }

        if ($accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user->load(['role.roleMenu', 'distributor', 'expedition', 'organizationAssignments']);
    }

    /**
     * Update an existing user with optional custom permissions.
     *
     * @param int $id
     * @param array $data
     * @return User|null
     */
    public function updateUser(int $id, array $data): ?User
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems'], $data['id_distributor']);

        // Handle actions, custom_permissions or permissions alias
        if (array_key_exists('actions', $data) || array_key_exists('custom_permissions', $data) || array_key_exists('permissions', $data)) {
            $rawPerms = $data['actions'] ?? $data['custom_permissions'] ?? $data['permissions'] ?? null;
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
        }
        unset($data['actions'], $data['permissions']);

        $orgInput = $data['organization_assignment'] ?? $data['organizational_assignment'] ?? $data['distribution_rule'] ?? $data['distribution_rules'] ?? null;
        if ($orgInput === null) {
            $hasOrgKeys = false;
            $tempOrg = [];
            foreach (['warehouses', 'warehouse', 'branches', 'branch', 'business_units', 'business_unit', 'departments', 'department', 'expeditions', 'expedition', 'distributors', 'distributor'] as $k) {
                if (array_key_exists($k, $data)) {
                    $tempOrg[$k] = $data[$k];
                    $hasOrgKeys = true;
                }
            }
            if ($hasOrgKeys) {
                $orgInput = $tempOrg;
            }
        }
        unset(
            $data['organization_assignment'],
            $data['organizational_assignment'],
            $data['distribution_rule'],
            $data['distribution_rules'],
            $data['warehouses'],
            $data['branches'],
            $data['business_units'],
            $data['departments'],
            $data['expeditions'],
            $data['distributors']
        );

        if (isset($data['unit']) && !isset($data['units'])) {
            $data['units'] = $data['unit'];
        }
        unset($data['unit']);

        $isProductionUser = !empty($data['whs_code']) ||
            !empty($data['units']) ||
            !empty($data['ocr_code']) ||
            !empty($data['ocr_code2']) ||
            !empty($data['ocr_code3']);

        if ($isProductionUser && empty($data['production_code'])) {
            $user = User::find($id);
            if ($user && empty($user->production_code)) {
                $data['production_code'] = User::generateProductionCode();
            }
        }

        $user = $this->userRepository->update($id, $data);

        if ($user && $orgInput !== null) {
            $this->syncOrganizationAssignments($user, $orgInput);
        }

        if ($user && $accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user ? $user->load(['role.roleMenu', 'distributor', 'expedition', 'organizationAssignments']) : null;
    }

    /**
     * Synchronize user organization assignments (distribution rules) to user_organization_assignments table.
     *
     * @param User $user
     * @param mixed $orgData
     * @return void
     */
    public function syncOrganizationAssignments(User $user, mixed $orgData): void
    {
        if ($orgData === null) {
            return;
        }

        if (is_string($orgData)) {
            $decoded = json_decode($orgData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $orgData = $decoded;
            }
        }

        if (!is_array($orgData)) {
            return;
        }

        // Map of standard category types to possible aliases in FE payload
        $categoryMap = [
            'warehouse' => ['warehouses', 'warehouse', 'whs_code', 'whs_codes', 'whs'],
            'branch' => ['branches', 'branch', 'cabang', 'ocr_code', 'ocr_codes'],
            'business_unit' => ['business_units', 'business_unit', 'bisnis_unit', 'ocr_code2'],
            'department' => ['departments', 'department', 'departemen', 'ocr_code3'],
            'expedition' => ['expeditions', 'expedition', 'ekspedisi', 'expedition_code'],
            'distributor' => ['distributors', 'distributor', 'customer', 'code_customer'],
        ];

        // Delete existing assignments for this user
        \App\Models\UserOrganizationAssignment::where('user_id', $user->id)->delete();

        $recordsToInsert = [];

        foreach ($categoryMap as $type => $aliases) {
            $rawValues = null;

            foreach ($aliases as $alias) {
                if (isset($orgData[$alias])) {
                    $rawValues = $orgData[$alias];
                    break;
                }
            }

            if ($rawValues === null) {
                continue;
            }

            if (!is_array($rawValues)) {
                $rawValues = [$rawValues];
            }

            foreach ($rawValues as $val) {
                $itemVal = null;
                $itemName = null;

                if (is_array($val)) {
                    $itemVal = $val['value'] ?? $val['code'] ?? $val['id'] ?? null;
                    $itemName = $val['label'] ?? $val['name'] ?? null;
                } elseif (is_scalar($val)) {
                    $itemVal = (string) $val;
                }

                if ($itemVal !== null && trim((string) $itemVal) !== '' && trim((string) $itemVal) !== '?') {
                    $cleanVal = trim((string) $itemVal);
                    $recordsToInsert[] = [
                        'user_id' => $user->id,
                        'type' => $type,
                        'value' => $cleanVal,
                        'name' => $itemName ? trim((string) $itemName) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($recordsToInsert)) {
            // Deduplicate by user_id + type + value
            $uniqueRecords = [];
            foreach ($recordsToInsert as $rec) {
                $key = $rec['type'] . '_' . $rec['value'];
                $uniqueRecords[$key] = $rec;
            }
            \App\Models\UserOrganizationAssignment::insert(array_values($uniqueRecords));
        }
    }

    /**
     * Delete a user.
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}

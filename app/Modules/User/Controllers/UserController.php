<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Services\UserCrudService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponseFormatter;

    protected UserCrudService $userService;

    /**
     * UserController constructor.
     *
     * @param UserCrudService $userService
     */
    public function __construct(UserCrudService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the users.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return $this->successResponse($users, 'Daftar user berhasil diambil.');
    }

    /**
     * Display the specified user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            abort(404, 'User tidak ditemukan.');
        }

        return $this->successResponse($user, 'Detail user berhasil diambil.');
    }

    /**
     * Store a newly created user in storage.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return $this->successResponse($user, 'User berhasil dibuat.', 200);
    }

    /**
     * Update the specified user in storage.
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->updateUser($id, $request->validated());

        if (!$user) {
            abort(404, 'User tidak ditemukan.');
        }

        return $this->successResponse($user, 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified user from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser($id);

        if (!$deleted) {
            abort(404, 'User tidak ditemukan.');
        }

        return $this->successResponse(null, 'User berhasil dihapus.');
    }
}

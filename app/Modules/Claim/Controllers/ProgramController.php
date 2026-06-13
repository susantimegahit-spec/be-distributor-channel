<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Services\ProgramService;
use App\Modules\Claim\Requests\StoreProgramRequest;
use App\Modules\Claim\Requests\UpdateProgramRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProgramController extends Controller
{
    /**
     * @var ProgramService
     */
    protected ProgramService $programService;

    /**
     * ProgramController constructor.
     *
     * @param ProgramService $programService
     */
    public function __construct(ProgramService $programService)
    {
        $this->programService = $programService;
    }

    /**
     * List all programs.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $programs = $this->programService->listPrograms($filters, $request->get('per_page', 15));
        
        return response()->json($programs);
    }

    /**
     * Create a program.
     *
     * @param StoreProgramRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProgramRequest $request)
    {
        $data = $request->validated();
        // Fallback to 'admin' if unauthenticated during tests/dev
        $data['created_by'] = $request->user()->username ?? 'admin';
        $program = $this->programService->createProgram($data);

        return response()->json($program, Response::HTTP_CREATED);
    }

    /**
     * Show detail of a program.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $program = $this->programService->getProgramDetail((int)$id);
        if (!$program) {
            return response()->json(['message' => 'Program tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($program);
    }

    /**
     * Update program.
     *
     * @param UpdateProgramRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProgramRequest $request, $id)
    {
        $program = $this->programService->updateProgram((int)$id, $request->validated());

        return response()->json($program);
    }

    /**
     * Delete a program.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $deleted = $this->programService->deleteProgram((int)$id);

        return response()->json(['success' => $deleted]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Department::with('levels')
                ->where('school_id', $this->schoolId($request))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->where('school_id', $user->school_id)],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('departments')->where('school_id', $user->school_id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $department = Department::create([
            ...$validated,
            'school_id' => $user->school_id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json(['message' => 'Department created successfully.', 'data' => $department->load('levels')], 201);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        abort_unless($department->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $department->load('levels')]);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($department->school_id === $user->school_id, 404);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where('school_id', $user->school_id)->ignore($department->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('departments')->where('school_id', $user->school_id)->ignore($department->id),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $department->update($validated);

        return response()->json(['message' => 'Department updated successfully.', 'data' => $department->refresh()->load('levels')]);
    }

    public function activate(Request $request, Department $department): JsonResponse
    {
        return $this->setStatus($request, $department, 'active');
    }

    public function deactivate(Request $request, Department $department): JsonResponse
    {
        return $this->setStatus($request, $department, 'inactive');
    }

    public function storeLevel(Request $request, Department $department): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($department->school_id === $user->school_id, 404);

        $validated = $request->validate([
            'name' => ['required_without:level_id', 'string', 'max:255'],
            'level_id' => [
                'required_without:name',
                'integer',
                Rule::exists('levels', 'id')->where('school_id', $user->school_id),
            ],
        ]);

        $level = isset($validated['level_id'])
            ? Level::where('school_id', $user->school_id)->findOrFail($validated['level_id'])
            : Level::firstOrCreate(
                [
                    'school_id' => $user->school_id,
                    'name' => trim($validated['name']),
                ],
                ['status' => 'active'],
            );

        $department->levels()->syncWithoutDetaching([
            $level->id => ['school_id' => $user->school_id],
        ]);

        return response()->json([
            'message' => 'Level added to department successfully.',
            'data' => $department->refresh()->load('levels'),
        ], 201);
    }

    public function destroyLevel(Request $request, Department $department, Level $level): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($department->school_id === $user->school_id && $level->school_id === $user->school_id, 404);

        $department->levels()->detach($level->id);

        return response()->json([
            'message' => 'Level removed from department successfully.',
            'data' => $department->refresh()->load('levels'),
        ]);
    }

    private function setStatus(Request $request, Department $department, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($department->school_id === $user->school_id, 404);

        $department->update(['status' => $status]);

        return response()->json(['message' => "Department {$status} successfully.", 'data' => $department->load('levels')]);
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user->school_id;
    }

    private function requireCatalogManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageCatalog(), 403);

        return $user;
    }
}

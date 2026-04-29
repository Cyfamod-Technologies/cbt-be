<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Department::where('school_id', $this->schoolId($request))
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

        return response()->json(['message' => 'Department created successfully.', 'data' => $department], 201);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        abort_unless($department->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $department]);
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

        return response()->json(['message' => 'Department updated successfully.', 'data' => $department->refresh()]);
    }

    public function activate(Request $request, Department $department): JsonResponse
    {
        return $this->setStatus($request, $department, 'active');
    }

    public function deactivate(Request $request, Department $department): JsonResponse
    {
        return $this->setStatus($request, $department, 'inactive');
    }

    private function setStatus(Request $request, Department $department, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($department->school_id === $user->school_id, 404);

        $department->update(['status' => $status]);

        return response()->json(['message' => "Department {$status} successfully.", 'data' => $department]);
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

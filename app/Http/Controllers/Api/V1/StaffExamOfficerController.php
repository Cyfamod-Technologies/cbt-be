<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffExamOfficer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffExamOfficerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        return response()->json([
            'data' => StaffExamOfficer::with(['staff', 'session', 'semester', 'department', 'level'])
                ->where('school_id', $schoolId)
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        $validated = $request->validate($this->rules($actor));

        $this->validateScope($validated);

        $duplicate = StaffExamOfficer::where('school_id', $actor->school_id)
            ->where('staff_id', $validated['staff_id'])
            ->where('session_id', $validated['session_id'])
            ->where('semester_id', $validated['semester_id'])
            ->where('department_id', $validated['department_id'])
            ->where('scope', $validated['scope'])
            ->when(
                $validated['scope'] === StaffExamOfficer::SCOPE_DEPARTMENT_LEVEL,
                fn ($query) => $query->where('level_id', $validated['level_id']),
                fn ($query) => $query->whereNull('level_id'),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'staff_id' => ['This exam officer assignment already exists.'],
            ]);
        }

        $assignment = StaffExamOfficer::create([
            ...$validated,
            'school_id' => $actor->school_id,
            'level_id' => $validated['scope'] === StaffExamOfficer::SCOPE_DEPARTMENT
                ? null
                : $validated['level_id'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Exam officer assignment created successfully.',
            'data' => $assignment->load(['staff', 'session', 'semester', 'department', 'level']),
        ], 201);
    }

    public function destroy(Request $request, StaffExamOfficer $staffExamOfficer): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($staffExamOfficer->school_id === $actor->school_id, 404);

        $staffExamOfficer->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $actor): array
    {
        $schoolId = $actor->school_id;

        return [
            'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'session_id' => ['required', 'integer', Rule::exists('sessions', 'id')->where('school_id', $schoolId)],
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'id')->where('school_id', $schoolId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'level_id' => ['nullable', 'integer', Rule::exists('levels', 'id')->where('school_id', $schoolId)],
            'scope' => ['required', Rule::in([StaffExamOfficer::SCOPE_DEPARTMENT, StaffExamOfficer::SCOPE_DEPARTMENT_LEVEL])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateScope(array $validated): void
    {
        if ($validated['scope'] === StaffExamOfficer::SCOPE_DEPARTMENT && ! empty($validated['level_id'])) {
            throw ValidationException::withMessages([
                'level_id' => ['Level must be empty for department-wide exam officers.'],
            ]);
        }

        if ($validated['scope'] === StaffExamOfficer::SCOPE_DEPARTMENT_LEVEL && empty($validated['level_id'])) {
            throw ValidationException::withMessages([
                'level_id' => ['Level is required for department-level exam officers.'],
            ]);
        }
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user->school_id;
    }

    private function requireUserManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageUsers(), 403);

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Course::with(['department', 'level', 'semester'])
                ->where('school_id', $this->schoolId($request))
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        $validated = $this->validateCourse($request, $user);

        $course = Course::create([
            ...$validated,
            'school_id' => $user->school_id,
            'status' => $validated['status'] ?? 'active',
        ])->load(['department', 'level', 'semester']);

        return response()->json(['message' => 'Course created successfully.', 'data' => $course], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $course->load(['department', 'level', 'semester'])]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($course->school_id === $user->school_id, 404);

        $validated = $this->validateCourse($request, $user, $course);
        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully.',
            'data' => $course->refresh()->load(['department', 'level', 'semester']),
        ]);
    }

    public function activate(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'active');
    }

    public function deactivate(Request $request, Course $course): JsonResponse
    {
        return $this->setStatus($request, $course, 'inactive');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourse(Request $request, User $user, ?Course $course = null): array
    {
        $schoolId = $user->school_id;
        $required = $course ? 'sometimes' : 'required';
        $departmentId = $request->input('department_id', $course?->department_id);
        $levelId = $request->input('level_id', $course?->level_id);
        $semesterId = $request->input('semester_id', $course?->semester_id);

        $validated = $request->validate([
            'code' => [
                $required,
                'string',
                'max:80',
            ],
            'title' => [$required, 'string', 'max:255'],
            'department_id' => [
                $required,
                'integer',
                Rule::exists('departments', 'id')->where('school_id', $schoolId),
            ],
            'level_id' => [
                $required,
                'integer',
                Rule::exists('levels', 'id')->where('school_id', $schoolId),
            ],
            'semester_id' => [
                $required,
                'integer',
                Rule::exists('semesters', 'id')->where('school_id', $schoolId),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $candidate = [
            'code' => $validated['code'] ?? $course?->code,
            'department_id' => $validated['department_id'] ?? $course?->department_id,
            'level_id' => $validated['level_id'] ?? $course?->level_id,
            'semester_id' => $validated['semester_id'] ?? $course?->semester_id,
        ];

        $duplicate = Course::where('school_id', $schoolId)
            ->where('code', $candidate['code'])
            ->where('department_id', $candidate['department_id'])
            ->where('level_id', $candidate['level_id'])
            ->where('semester_id', $candidate['semester_id'])
            ->when($course, fn ($query) => $query->whereKeyNot($course->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ['This course code already exists for the selected department, level, and semester.'],
            ]);
        }

        return $validated;
    }

    private function setStatus(Request $request, Course $course, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($course->school_id === $user->school_id, 404);

        $course->update(['status' => $status]);

        return response()->json(['message' => "Course {$status} successfully.", 'data' => $course->load(['department', 'level', 'semester'])]);
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

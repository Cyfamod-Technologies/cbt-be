<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        $query = Course::with(['department', 'level', 'semester'])
            ->where('school_id', $user->school_id)
            ->orderBy('code');

        if ($user->role === User::ROLE_STAFF) {
            $assignedIds = $user->staff?->courseAssignments()->pluck('course_id') ?? collect();
            $query->whereIn('id', $assignedIds);
        }

        return response()->json(['data' => $query->get()]);
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

    public function destroy(Request $request, Course $course): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($course->school_id === $user->school_id, 404);

        $links = [
            ['table' => 'assessments',               'column' => 'course_id', 'label' => 'assessments',           'unlinkable' => false],
            ['table' => 'staff_course_assignments',  'column' => 'course_id', 'label' => 'lecturer assignments',  'unlinkable' => true],
            ['table' => 'student_course_enrollments','column' => 'course_id', 'label' => 'student enrollments',   'unlinkable' => true],
        ];

        $blocked = $this->handleLinkedOrCascade($request, $links, $course->id, "this course");
        if ($blocked !== null) {
            return $blocked;
        }

        $course->delete();
        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourse(Request $request, User $user, ?Course $course = null): array
    {
        $schoolId = $user->school_id;
        $required = $course ? 'sometimes' : 'required';

        $validated = $request->validate([
            'code' => [
                $required,
                'string',
                'max:80',
            ],
            'title' => [$required, 'string', 'max:255'],
            'credit_unit' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('school_id', $schoolId),
            ],
            'level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('levels', 'id')->where('school_id', $schoolId)],
            'semester_id' => ['sometimes', 'nullable', 'integer', Rule::exists('semesters', 'id')->where('school_id', $schoolId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $candidate = [
            'code' => $validated['code'] ?? $course?->code,
            'department_id' => $validated['department_id'] ?? $course?->department_id,
        ];

        $deptId = $candidate['department_id'];
        $duplicateQuery = Course::where('school_id', $schoolId)
            ->where('code', $candidate['code'])
            ->when($course, fn ($query) => $query->whereKeyNot($course->id));
        $duplicate = $deptId
            ? (clone $duplicateQuery)->where('department_id', $deptId)->exists()
            : (clone $duplicateQuery)->whereNull('department_id')->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ['This course code already exists for the selected department.'],
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

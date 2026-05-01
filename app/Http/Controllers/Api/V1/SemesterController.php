<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SemesterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Semester::with('session')
                ->where('school_id', $this->schoolId($request))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);

        $validated = $request->validate([
            'session_id' => ['required', 'integer', Rule::exists('sessions', 'id')->where('school_id', $user->school_id)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('semesters')
                    ->where('school_id', $user->school_id)
                    ->where('session_id', $request->input('session_id')),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $semester = Semester::create([
            ...$validated,
            'school_id' => $user->school_id,
            'status' => $validated['status'] ?? 'active',
        ])->load('session');

        return response()->json(['message' => 'Semester created successfully.', 'data' => $semester], 201);
    }

    public function show(Request $request, Semester $semester): JsonResponse
    {
        abort_unless($semester->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $semester]);
    }

    public function update(Request $request, Semester $semester): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($semester->school_id === $user->school_id, 404);

        $validated = $request->validate([
            'session_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('sessions', 'id')->where('school_id', $user->school_id),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('semesters')
                    ->where('school_id', $user->school_id)
                    ->where('session_id', $request->input('session_id', $semester->session_id))
                    ->ignore($semester->id),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $semester->update($validated);

        return response()->json(['message' => 'Semester updated successfully.', 'data' => $semester->refresh()->load('session')]);
    }

    public function activate(Request $request, Semester $semester): JsonResponse
    {
        return $this->setStatus($request, $semester, 'active');
    }

    public function deactivate(Request $request, Semester $semester): JsonResponse
    {
        return $this->setStatus($request, $semester, 'inactive');
    }

    public function setCurrent(Request $request, Semester $semester): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($semester->school_id === $user->school_id, 404);

        $semester->update(['status' => 'active']);
        SchoolSetting::updateOrCreate(
            ['school_id' => $user->school_id],
            ['current_semester_id' => $semester->id],
        );

        return response()->json(['message' => 'Current semester updated successfully.', 'data' => $semester->refresh()]);
    }

    private function setStatus(Request $request, Semester $semester, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($semester->school_id === $user->school_id, 404);

        $semester->update(['status' => $status]);

        return response()->json(['message' => "Semester {$status} successfully.", 'data' => $semester->load('session')]);
    }

    public function destroy(Request $request, Semester $semester): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($semester->school_id === $user->school_id, 404);

        $links = [
            ['table' => 'assessments',              'column' => 'semester_id', 'label' => 'assessments',              'unlinkable' => false],
            ['table' => 'staff_course_assignments', 'column' => 'semester_id', 'label' => 'lecturer assignments',     'unlinkable' => true],
            ['table' => 'staff_exam_officers',      'column' => 'semester_id', 'label' => 'exam officer assignments', 'unlinkable' => true],
        ];

        $blocked = $this->handleLinkedOrCascade($request, $links, $semester->id, "this semester");
        if ($blocked !== null) {
            return $blocked;
        }

        $semester->delete();
        return response()->json(null, 204);
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

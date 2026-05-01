<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Level::where('school_id', $this->schoolId($request))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('levels')->where('school_id', $user->school_id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $level = Level::create([
            ...$validated,
            'school_id' => $user->school_id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json(['message' => 'Level created successfully.', 'data' => $level], 201);
    }

    public function show(Request $request, Level $level): JsonResponse
    {
        abort_unless($level->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $level]);
    }

    public function update(Request $request, Level $level): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($level->school_id === $user->school_id, 404);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('levels')->where('school_id', $user->school_id)->ignore($level->id),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $level->update($validated);

        return response()->json(['message' => 'Level updated successfully.', 'data' => $level->refresh()]);
    }

    public function activate(Request $request, Level $level): JsonResponse
    {
        return $this->setStatus($request, $level, 'active');
    }

    public function deactivate(Request $request, Level $level): JsonResponse
    {
        return $this->setStatus($request, $level, 'inactive');
    }

    private function setStatus(Request $request, Level $level, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($level->school_id === $user->school_id, 404);

        $level->update(['status' => $status]);

        return response()->json(['message' => "Level {$status} successfully.", 'data' => $level]);
    }

    public function destroy(Request $request, Level $level): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($level->school_id === $user->school_id, 404);

        $links = [
            ['table' => 'users',                    'column' => 'level_id', 'label' => 'students',                  'unlinkable' => false],
            ['table' => 'courses',                  'column' => 'level_id', 'label' => 'courses',                   'unlinkable' => false],
            ['table' => 'assessments',              'column' => 'level_id', 'label' => 'assessments',               'unlinkable' => false],
            ['table' => 'staff_course_assignments', 'column' => 'level_id', 'label' => 'lecturer assignments',      'unlinkable' => true],
            ['table' => 'staff_exam_officers',      'column' => 'level_id', 'label' => 'exam officer assignments',  'unlinkable' => true],
        ];

        $blocked = $this->handleLinkedOrCascade($request, $links, $level->id, "this level");
        if ($blocked !== null) {
            return $blocked;
        }

        $level->delete();
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

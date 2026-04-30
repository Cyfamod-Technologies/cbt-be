<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $this->requireManager($request);

        return response()->json([
            'data' => Assessment::with(['session', 'semester', 'department', 'level', 'course'])
                ->withCount(['questions', 'attempts'])
                ->where('school_id', $actor->school_id)
                ->latest('id')
                ->get(),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);
        $schoolSetting = SchoolSetting::where('school_id', $student->school_id)->first();
        $now = Carbon::now();

        $query = Assessment::with(['session', 'semester', 'department', 'level', 'course'])
            ->withCount(['questions', 'attempts'])
            ->where('school_id', $student->school_id)
            ->where('status', Assessment::STATUS_PUBLISHED)
            ->where(function ($builder) use ($now): void {
                $builder->whereNull('start_time')->orWhere('start_time', '<=', $now);
            })
            ->where(function ($builder) use ($now): void {
                $builder->whereNull('end_time')->orWhere('end_time', '>=', $now);
            })
            ->where('department_id', $student->department_id);

        if ($schoolSetting?->current_session_id) {
            $query->where('session_id', $schoolSetting->current_session_id);
        }

        if ($schoolSetting?->current_semester_id) {
            $query->where('semester_id', $schoolSetting->current_semester_id);
        }

        if ($student->level_id) {
            $query->where(function ($builder) use ($student): void {
                $builder->whereNull('level_id')->orWhere('level_id', $student->level_id);
            });
        }

        return response()->json(['data' => $query->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireManager($request);
        $validated = $this->validateAssessment($request, $actor);

        if (($validated['allow_multiple_attempts'] ?? false) === false) {
            $validated['max_attempts'] = 1;
        }

        $assessment = Assessment::create([
            ...$validated,
            'school_id' => $actor->school_id,
            'created_by' => $actor->id,
            'status' => $validated['status'] ?? Assessment::STATUS_DRAFT,
        ])->load(['session', 'semester', 'department', 'level', 'course']);

        return response()->json([
            'message' => 'Assessment created successfully.',
            'data' => $assessment,
        ], 201);
    }

    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($assessment->school_id === $user->school_id, 404);

        return response()->json([
            'data' => $assessment->load(['session', 'semester', 'department', 'level', 'course', 'questions.options', 'attempts']),
        ]);
    }

    public function update(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $validated = $this->validateAssessment($request, $actor, $assessment);

        if (($validated['allow_multiple_attempts'] ?? $assessment->allow_multiple_attempts) === false) {
            $validated['max_attempts'] = 1;
        }

        $assessment->update($validated);

        return response()->json([
            'message' => 'Assessment updated successfully.',
            'data' => $assessment->refresh()->load(['session', 'semester', 'department', 'level', 'course', 'questions.options']),
        ]);
    }

    public function destroy(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $assessment->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $assessment->update([
            'status' => Assessment::STATUS_PUBLISHED,
            'published_at' => $assessment->published_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Assessment published successfully.',
            'data' => $assessment->refresh()->load(['session', 'semester', 'department', 'level', 'course', 'questions.options']),
        ]);
    }

    public function close(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $assessment->update([
            'status' => Assessment::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Assessment closed successfully.',
            'data' => $assessment->refresh()->load(['session', 'semester', 'department', 'level', 'course', 'questions.options']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAssessment(Request $request, User $actor, ?Assessment $assessment = null): array
    {
        $schoolId = $actor->school_id;
        $required = $assessment ? 'sometimes' : 'required';

        $validated = $request->validate([
            'code' => [$required, 'string', 'max:80'],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'session_id' => [$required, 'integer', Rule::exists('sessions', 'id')->where('school_id', $schoolId)],
            'semester_id' => [$required, 'integer', Rule::exists('semesters', 'id')->where('school_id', $schoolId)],
            'department_id' => [$required, 'integer', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('levels', 'id')->where('school_id', $schoolId)],
            'course_id' => ['sometimes', 'nullable', 'integer', Rule::exists('courses', 'id')->where('school_id', $schoolId)],
            'duration_minutes' => ['sometimes', 'integer', 'min:0'],
            'total_questions' => ['sometimes', 'integer', 'min:0'],
            'total_marks' => ['sometimes', 'numeric', 'min:0'],
            'pass_mark' => ['sometimes', 'numeric', 'min:0'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'shuffle_options' => ['sometimes', 'boolean'],
            'allow_review' => ['sometimes', 'boolean'],
            'show_score' => ['sometimes', 'boolean'],
            'show_answers' => ['sometimes', 'boolean'],
            'allow_multiple_attempts' => ['sometimes', 'boolean'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_time'],
            'status' => ['sometimes', Rule::in([Assessment::STATUS_DRAFT, Assessment::STATUS_PUBLISHED, Assessment::STATUS_CLOSED])],
        ]);

        $candidateCode = $validated['code'] ?? $assessment?->code;
        if ($candidateCode) {
            $duplicate = Assessment::where('school_id', $schoolId)
                ->where('code', $candidateCode)
                ->when($assessment, fn ($query) => $query->whereKeyNot($assessment->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => ['This assessment code already exists for the selected school.'],
                ]);
            }
        }

        return $validated;
    }

    private function requireSchoolUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user;
    }

    private function requireManager(Request $request): User
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($user->canManageAssessments(), 403);

        return $user;
    }

    private function requireStudent(Request $request): User
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($user->canTakeExams(), 403);

        return $user;
    }
}
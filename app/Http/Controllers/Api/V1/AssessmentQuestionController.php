<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionOption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentQuestionController extends Controller
{
    public function index(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        return response()->json([
            'data' => $assessment->questions()->with('options')->get(),
        ]);
    }

    public function store(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $validated = $this->validateQuestion($request);

        $question = DB::transaction(function () use ($validated, $actor, $assessment): AssessmentQuestion {
            $question = AssessmentQuestion::create([
                ...Arr::except($validated, ['options']),
                'school_id' => $actor->school_id,
                'assessment_id' => $assessment->id,
                'created_by' => $actor->id,
            ]);

            $this->syncOptions($question, $validated['options'] ?? []);

            return $question->load('options');
        });

        $this->refreshAssessmentCounts($assessment);

        return response()->json([
            'message' => 'Question created successfully.',
            'data' => $question,
        ], 201);
    }

    public function update(Request $request, AssessmentQuestion $assessmentQuestion): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessmentQuestion->school_id === $actor->school_id, 404);

        $assessmentQuestion->load('assessment');
        $validated = $this->validateQuestion($request, $assessmentQuestion);

        $question = DB::transaction(function () use ($validated, $assessmentQuestion): AssessmentQuestion {
            $assessmentQuestion->update(Arr::except($validated, ['options']));
            $assessmentQuestion->options()->delete();
            $this->syncOptions($assessmentQuestion, $validated['options'] ?? []);

            return $assessmentQuestion->refresh()->load('options');
        });

        $this->refreshAssessmentCounts($assessmentQuestion->assessment);

        return response()->json([
            'message' => 'Question updated successfully.',
            'data' => $question,
        ]);
    }

    public function destroy(Request $request, AssessmentQuestion $assessmentQuestion): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessmentQuestion->school_id === $actor->school_id, 404);

        $assessment = $assessmentQuestion->assessment()->first();
        $assessmentQuestion->delete();

        if ($assessment instanceof Assessment) {
            $this->refreshAssessmentCounts($assessment);
        }

        return response()->json(null, 204);
    }

    public function import(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($assessment->school_id === $actor->school_id, 404);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.question_text' => ['required', 'string'],
            'rows.*.question_type' => ['sometimes', Rule::in([
                AssessmentQuestion::TYPE_MULTIPLE_CHOICE,
                AssessmentQuestion::TYPE_MULTIPLE_SELECT,
                AssessmentQuestion::TYPE_TRUE_FALSE,
                AssessmentQuestion::TYPE_SHORT_ANSWER,
            ])],
            'rows.*.marks' => ['sometimes', 'numeric', 'min:0'],
            'rows.*.correct_answer' => ['sometimes', 'nullable', 'string'],
            'rows.*.options' => ['sometimes', 'array'],
            'rows.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'rows.*.explanation' => ['sometimes', 'nullable', 'string'],
        ]);

        $created = DB::transaction(function () use ($validated, $actor, $assessment) {
            $questions = [];

            foreach ($validated['rows'] as $row) {
                $question = AssessmentQuestion::create([
                    'school_id' => $actor->school_id,
                    'assessment_id' => $assessment->id,
                    'created_by' => $actor->id,
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'] ?? AssessmentQuestion::TYPE_MULTIPLE_CHOICE,
                    'marks' => $row['marks'] ?? 1,
                    'sort_order' => $row['sort_order'] ?? 0,
                    'correct_answer' => $row['correct_answer'] ?? null,
                    'explanation' => $row['explanation'] ?? null,
                ]);

                $this->syncOptions($question, $row['options'] ?? []);
                $questions[] = $question->load('options');
            }

            return $questions;
        });

        $this->refreshAssessmentCounts($assessment);

        return response()->json([
            'message' => 'Questions imported successfully.',
            'data' => $created,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request, ?AssessmentQuestion $question = null): array
    {
        $required = $question ? 'sometimes' : 'required';

        return $request->validate([
            'question_text' => [$required, 'string'],
            'question_type' => ['sometimes', Rule::in([
                AssessmentQuestion::TYPE_MULTIPLE_CHOICE,
                AssessmentQuestion::TYPE_MULTIPLE_SELECT,
                AssessmentQuestion::TYPE_TRUE_FALSE,
                AssessmentQuestion::TYPE_SHORT_ANSWER,
            ])],
            'marks' => ['sometimes', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'correct_answer' => ['sometimes', 'nullable', 'string'],
            'explanation' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*.option_text' => ['sometimes', 'string'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'options.*.image_url' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private function syncOptions(AssessmentQuestion $question, array $options): void
    {
        foreach (array_values($options) as $index => $option) {
            if (is_string($option)) {
                $option = [
                    'option_text' => $option,
                    'is_correct' => false,
                ];
            }

            if (! is_array($option) || empty($option['option_text'])) {
                continue;
            }

            AssessmentQuestionOption::create([
                'school_id' => $question->school_id,
                'question_id' => $question->id,
                'option_text' => $option['option_text'],
                'sort_order' => $option['sort_order'] ?? $index,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'image_url' => $option['image_url'] ?? null,
            ]);
        }
    }

    private function refreshAssessmentCounts(Assessment $assessment): void
    {
        $assessment->update([
            'total_questions' => $assessment->questions()->count(),
            'total_marks' => (float) $assessment->questions()->sum('marks'),
        ]);
    }

    private function requireManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);
        abort_unless($user->canManageAssessments(), 403);

        return $user;
    }
}
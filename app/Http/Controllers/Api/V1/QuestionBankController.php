<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\QuestionBankItemOption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);
        abort_unless($user->canManageAssessments(), 403);

        $query = QuestionBankItem::with('options')
            ->where('school_id', $user->school_id);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireManager($request);

        $validated = $this->validateQuestion($request);

        $item = DB::transaction(function () use ($validated, $actor): QuestionBankItem {
            $item = QuestionBankItem::create([
                ...collect($validated)->except('options')->all(),
                'school_id' => $actor->school_id,
                'created_by' => $actor->id,
            ]);

            $this->syncOptions($item, $validated['options'] ?? []);

            return $item->load('options');
        });

        return response()->json([
            'message' => 'Question bank item created successfully.',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, QuestionBankItem $questionBankItem): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($questionBankItem->school_id === $actor->school_id, 404);

        $validated = $this->validateQuestion($request, $questionBankItem);

        $item = DB::transaction(function () use ($validated, $questionBankItem): QuestionBankItem {
            $questionBankItem->update(collect($validated)->except('options')->all());
            $questionBankItem->options()->delete();
            $this->syncOptions($questionBankItem, $validated['options'] ?? []);

            return $questionBankItem->refresh()->load('options');
        });

        return response()->json([
            'message' => 'Question bank item updated successfully.',
            'data' => $item,
        ]);
    }

    public function destroy(Request $request, QuestionBankItem $questionBankItem): JsonResponse
    {
        $actor = $this->requireManager($request);
        abort_unless($questionBankItem->school_id === $actor->school_id, 404);

        $questionBankItem->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request, ?QuestionBankItem $item = null): array
    {
        $required = $item ? 'sometimes' : 'required';

        return $request->validate([
            'question_text' => [$required, 'string'],
            'question_type' => ['sometimes', Rule::in([
                QuestionBankItem::TYPE_MULTIPLE_CHOICE,
                QuestionBankItem::TYPE_MULTIPLE_SELECT,
                QuestionBankItem::TYPE_TRUE_FALSE,
                QuestionBankItem::TYPE_SHORT_ANSWER,
            ])],
            'course_id' => ['sometimes', 'nullable', 'integer'],
            'marks' => ['sometimes', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'correct_answer' => ['sometimes', 'nullable', 'string'],
            'explanation' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*.option_text' => ['sometimes', 'string'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private function syncOptions(QuestionBankItem $item, array $options): void
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

            QuestionBankItemOption::create([
                'school_id' => $item->school_id,
                'question_id' => $item->id,
                'option_text' => $option['option_text'],
                'sort_order' => $option['sort_order'] ?? $index,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ]);
        }
    }

    private function requireManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);
        abort_unless($user->canManageAssessments(), 403);

        return $user;
    }
}

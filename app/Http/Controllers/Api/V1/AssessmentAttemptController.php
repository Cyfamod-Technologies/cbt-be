<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionOption;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentAttemptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireSchoolUser($request);

        $query = AssessmentAttempt::with(['assessment.session', 'assessment.semester', 'assessment.department', 'assessment.level', 'student'])
            ->where('school_id', $user->school_id)
            ->latest('id');

        if ($user->canManageAssessments()) {
            if ($request->integer('assessment_id')) {
                $query->where('assessment_id', $request->integer('assessment_id'));
            }

            return response()->json(['data' => $query->get()]);
        }

        abort_unless($user->canTakeExams(), 403);

        return response()->json(['data' => $query->where('student_id', $user->id)->get()]);
    }

    public function store(Request $request, Assessment $assessment): JsonResponse
    {
        $student = $this->requireStudent($request);
        abort_unless($assessment->school_id === $student->school_id, 404);
        $this->ensureAvailable($assessment, $student);

        $activeAttempt = AssessmentAttempt::where('school_id', $student->school_id)
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($activeAttempt) {
            return response()->json(['data' => $this->formatAttempt($activeAttempt->load(['assessment.questions.options', 'answers']))]);
        }

        if (! $assessment->allow_multiple_attempts) {
            $attemptCount = AssessmentAttempt::where('school_id', $student->school_id)
                ->where('assessment_id', $assessment->id)
                ->where('student_id', $student->id)
                ->count();

            if ($attemptCount > 0) {
                throw ValidationException::withMessages([
                    'assessment' => ['This assessment allows only one attempt.'],
                ]);
            }
        } elseif ($assessment->max_attempts && AssessmentAttempt::where('school_id', $student->school_id)
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->count() >= $assessment->max_attempts) {
            throw ValidationException::withMessages([
                'assessment' => ['You have reached the maximum number of attempts for this assessment.'],
            ]);
        }

        $attempt = AssessmentAttempt::create([
            'school_id' => $student->school_id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'created_by' => $student->id,
            'session_id' => $assessment->session_id,
            'semester_id' => $assessment->semester_id,
            'start_time' => now(),
            'status' => 'in_progress',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Assessment attempt started successfully.',
            'data' => $this->formatAttempt($attempt->load(['assessment.questions.options']))
        ], 201);
    }

    public function show(Request $request, AssessmentAttempt $assessmentAttempt): JsonResponse
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($assessmentAttempt->school_id === $user->school_id, 404);
        $this->authorizeAttemptAccess($user, $assessmentAttempt);

        return response()->json([
            'data' => $this->formatAttempt($assessmentAttempt->load(['assessment.questions.options', 'answers']))
        ]);
    }

    public function submit(Request $request, AssessmentAttempt $assessmentAttempt): JsonResponse
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($assessmentAttempt->school_id === $user->school_id, 404);
        $this->authorizeAttemptAccess($user, $assessmentAttempt);

        $assessmentAttempt->load(['assessment.questions.options', 'answers']);

        if ($assessmentAttempt->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'attempt' => ['This attempt has already been submitted.'],
            ]);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_ids' => ['sometimes', 'array'],
            'answers.*.option_ids.*' => ['integer'],
            'answers.*.answer_text' => ['sometimes', 'nullable', 'string'],
        ]);

        $graded = DB::transaction(function () use ($validated, $assessmentAttempt) {
            $questions = $assessmentAttempt->assessment->questions->keyBy('id');
            $totalMarks = 0.0;
            $score = 0.0;

            foreach ($validated['answers'] as $answerData) {
                $question = $questions->get((int) $answerData['question_id']);

                if (! $question instanceof AssessmentQuestion) {
                    continue;
                }

                $assessment = $this->gradeAnswer($question, $answerData);
                $totalMarks += (float) $question->marks;
                $score += $assessment['marks_awarded'];

                AssessmentAttemptAnswer::updateOrCreate(
                    [
                        'attempt_id' => $assessmentAttempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'school_id' => $assessmentAttempt->school_id,
                        'option_id' => $assessment['option_id'],
                        'answer_text' => $assessment['answer_text'],
                        'is_correct' => $assessment['is_correct'],
                        'marks_awarded' => $assessment['marks_awarded'],
                    ],
                );
            }

            $percentage = $totalMarks > 0 ? ($score / $totalMarks) * 100 : 0;
            $grade = $score >= (float) $assessmentAttempt->assessment->pass_mark ? 'pass' : 'fail';

            $assessmentAttempt->update([
                'end_time' => now(),
                'score' => $score,
                'total_marks' => $totalMarks,
                'percentage' => $percentage,
                'grade' => $grade,
                'status' => 'submitted',
            ]);

            return $assessmentAttempt->refresh()->load(['assessment.questions.options', 'answers.question.options', 'student']);
        });

        return response()->json([
            'message' => 'Assessment submitted successfully.',
            'data' => $this->formatAttempt($graded),
        ]);
    }

    private function formatAttempt(AssessmentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'school_id' => $attempt->school_id,
            'assessment_id' => $attempt->assessment_id,
            'student_id' => $attempt->student_id,
            'start_time' => $attempt->start_time,
            'end_time' => $attempt->end_time,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'grade' => $attempt->grade,
            'status' => $attempt->status,
            'assessment' => $attempt->assessment ? [
                'id' => $attempt->assessment->id,
                'code' => $attempt->assessment->code,
                'title' => $attempt->assessment->title,
                'duration_minutes' => $attempt->assessment->duration_minutes,
                'allow_review' => $attempt->assessment->allow_review,
                'show_answers' => $attempt->assessment->show_answers,
                'show_score' => $attempt->assessment->show_score,
                'questions' => $attempt->assessment->questions->map(fn (AssessmentQuestion $question): array => [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'marks' => $question->marks,
                    'sort_order' => $question->sort_order,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'options' => $question->options->map(fn (AssessmentQuestionOption $option): array => [
                        'id' => $option->id,
                        'option_text' => $option->option_text,
                        'sort_order' => $option->sort_order,
                        'is_correct' => $option->is_correct,
                        'image_url' => $option->image_url,
                    ])->values(),
                ])->values(),
            ] : null,
            'answers' => $attempt->answers->map(fn (AssessmentAttemptAnswer $answer): array => [
                'id' => $answer->id,
                'question_id' => $answer->question_id,
                'option_id' => $answer->option_id,
                'answer_text' => $answer->answer_text,
                'is_correct' => $answer->is_correct,
                'marks_awarded' => $answer->marks_awarded,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $answerData
     * @return array{option_id:int|null,answer_text:string|null,is_correct:bool,marks_awarded:float}
     */
    private function gradeAnswer(AssessmentQuestion $question, array $answerData): array
    {
        $optionIds = collect($answerData['option_ids'] ?? [])->map(fn ($value) => (int) $value)->values();
        $answerText = isset($answerData['answer_text']) ? trim((string) $answerData['answer_text']) : null;

        $correctOptions = $question->options->where('is_correct', true)->pluck('id')->sort()->values();
        $selectedOption = $question->options->firstWhere('id', $optionIds->first());

        if ($question->question_type === AssessmentQuestion::TYPE_SHORT_ANSWER) {
            $isCorrect = $answerText !== null && mb_strtolower($answerText) === mb_strtolower(trim((string) $question->correct_answer));

            return [
                'option_id' => null,
                'answer_text' => $answerText,
                'is_correct' => $isCorrect,
                'marks_awarded' => $isCorrect ? (float) $question->marks : 0.0,
            ];
        }

        if ($question->question_type === AssessmentQuestion::TYPE_MULTIPLE_SELECT) {
            $isCorrect = $optionIds->sort()->values()->all() === $correctOptions->all();

            return [
                'option_id' => $optionIds->first(),
                'answer_text' => $optionIds->isNotEmpty() ? json_encode($optionIds->values()->all()) : null,
                'is_correct' => $isCorrect,
                'marks_awarded' => $isCorrect ? (float) $question->marks : 0.0,
            ];
        }

        $isCorrect = $selectedOption?->is_correct ?? false;

        return [
            'option_id' => $selectedOption?->id,
            'answer_text' => $selectedOption?->option_text,
            'is_correct' => $isCorrect,
            'marks_awarded' => $isCorrect ? (float) $question->marks : 0.0,
        ];
    }

    private function ensureAvailable(Assessment $assessment, User $student): void
    {
        $schoolSetting = SchoolSetting::where('school_id', $student->school_id)->first();
        $now = Carbon::now();

        $sessionMatch = ! $schoolSetting?->current_session_id || (int) $assessment->session_id === (int) $schoolSetting->current_session_id;
        $semesterMatch = ! $schoolSetting?->current_semester_id || (int) $assessment->semester_id === (int) $schoolSetting->current_semester_id;
        $departmentMatch = (int) $assessment->department_id === (int) $student->department_id;
        $levelMatch = ! $assessment->level_id || (int) $assessment->level_id === (int) $student->level_id;
        $scheduleMatch = ($assessment->start_time === null || $assessment->start_time <= $now) && ($assessment->end_time === null || $assessment->end_time >= $now);

        if ($assessment->status !== Assessment::STATUS_PUBLISHED || ! $sessionMatch || ! $semesterMatch || ! $departmentMatch || ! $levelMatch || ! $scheduleMatch) {
            throw ValidationException::withMessages([
                'assessment' => ['This assessment is not available to your account right now.'],
            ]);
        }
    }

    private function authorizeAttemptAccess(User $user, AssessmentAttempt $attempt): void
    {
        if ($user->canManageAssessments()) {
            return;
        }

        abort_unless($attempt->student_id === $user->id, 403);
    }

    private function requireSchoolUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user;
    }

    private function requireStudent(Request $request): User
    {
        $user = $this->requireSchoolUser($request);
        abort_unless($user->canTakeExams(), 403);

        return $user;
    }
}
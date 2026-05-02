<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'course_id',
    'created_by',
    'question_text',
    'question_type',
    'marks',
    'sort_order',
    'correct_answer',
    'explanation',
])]
class QuestionBankItem extends Model
{
    use HasFactory;

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_MULTIPLE_SELECT = 'multiple_select';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_SHORT_ANSWER = 'short_answer';

    protected $casts = [
        'marks' => 'decimal:2',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionBankItemOption::class, 'question_id')->orderBy('sort_order')->orderBy('id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['school_id', 'name', 'is_current', 'status'])]
class AcademicSession extends Model
{
    use HasFactory;

    protected $table = 'sessions';

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function currentSetting(): HasOne
    {
        return $this->hasOne(SchoolSetting::class, 'current_session_id');
    }
}

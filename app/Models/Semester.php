<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['school_id', 'session_id', 'name', 'status'])]
class Semester extends Model
{
    use HasFactory;

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function currentSetting(): HasOne
    {
        return $this->hasOne(SchoolSetting::class, 'current_semester_id');
    }
}

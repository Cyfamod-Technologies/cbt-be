<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'user_id',
    'staff_id',
    'full_name',
    'email',
    'phone',
    'department_id',
    'status',
])]
class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function courseAssignments(): HasMany
    {
        return $this->hasMany(StaffCourseAssignment::class);
    }

    public function examOfficerAssignments(): HasMany
    {
        return $this->hasMany(StaffExamOfficer::class);
    }
}

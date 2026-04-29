<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['school_id', 'name', 'matric_no', 'student_id_no', 'department_id', 'level_id', 'phone', 'gender', 'employment_start_date', 'address', 'qualifications', 'photo_url', 'email', 'password', 'role', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_STUDENT = 'student';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isStaffLike(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_STAFF], true);
    }

    public function canManageSchools(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageCatalog(): bool
    {
        return $this->isAdmin();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageQuestions(): bool
    {
        return $this->isStaffLike();
    }

    public function canTakeExams(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * @return list<string>
     */
    public function tokenAbilities(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        $abilities = ['profile:read'];

        if ($this->role === self::ROLE_ADMIN) {
            return [
                ...$abilities,
                'catalog:manage',
                'users:manage',
                'questions:manage',
                'exams:manage',
                'results:manage',
            ];
        }

        if ($this->role === self::ROLE_STAFF) {
            return [
                ...$abilities,
                'questions:manage',
            ];
        }

        return [
            ...$abilities,
            'exams:take',
            'results:view-own',
        ];
    }
}

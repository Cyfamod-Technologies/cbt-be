<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('staff_id');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'staff_id']);
            $table->unique(['school_id', 'email']);
            $table->index(['school_id', 'department_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('staff_course_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(
                ['school_id', 'staff_id', 'session_id', 'semester_id', 'department_id', 'level_id', 'course_id'],
                'staff_course_assignment_unique',
            );
            $table->index(['school_id', 'staff_id']);
            $table->index(['school_id', 'course_id']);
        });

        Schema::create('staff_exam_officers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 40);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'staff_id']);
            $table->index(['school_id', 'department_id', 'level_id']);
        });

        $this->backfillStaffProfiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_exam_officers');
        Schema::dropIfExists('staff_course_assignments');
        Schema::dropIfExists('staff');
    }

    private function backfillStaffProfiles(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where('role', 'staff')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $schoolId = (int) $user->school_id;
                    $staffId = 'STF-'.$user->id;
                    $email = $user->email ?: sprintf('staff-%s@school-%s.local', $user->id, $schoolId);

                    DB::table('staff')->updateOrInsert(
                        ['user_id' => $user->id],
                        [
                            'school_id' => $schoolId,
                            'staff_id' => $staffId,
                            'full_name' => $user->name,
                            'email' => $email,
                            'phone' => $user->phone ?? null,
                            'department_id' => $user->department_id ?? null,
                            'status' => $user->status ?? 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }
};

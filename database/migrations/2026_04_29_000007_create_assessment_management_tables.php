<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->decimal('total_marks', 10, 2)->default(0);
            $table->decimal('pass_mark', 10, 2)->default(0);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('allow_review')->default(true);
            $table->boolean('show_score')->default(true);
            $table->boolean('show_answers')->default(false);
            $table->boolean('allow_multiple_attempts')->default(false);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'session_id', 'semester_id']);
            $table->index(['school_id', 'department_id', 'level_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question_text');
            $table->string('question_type', 40)->default('multiple_choice');
            $table->decimal('marks', 10, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'assessment_id']);
            $table->index(['school_id', 'question_type']);
        });

        Schema::create('assessment_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'question_id']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('total_marks', 10, 2)->default(0);
            $table->decimal('percentage', 10, 2)->default(0);
            $table->string('grade', 30)->nullable();
            $table->string('status', 30)->default('in_progress');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'assessment_id']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('assessment_attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('assessment_question_options')->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('marks_awarded', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index(['school_id', 'attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }
};
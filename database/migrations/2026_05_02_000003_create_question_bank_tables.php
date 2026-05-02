<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question_text');
            $table->string('question_type', 40)->default('multiple_choice');
            $table->decimal('marks', 10, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'course_id']);
            $table->index(['school_id', 'question_type']);
        });

        Schema::create('question_bank_item_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('question_bank_items')->cascadeOnDelete();
            $table->text('option_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_item_options');
        Schema::dropIfExists('question_bank_items');
    }
};

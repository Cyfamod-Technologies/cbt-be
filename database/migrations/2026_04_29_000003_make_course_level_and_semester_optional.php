<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE courses DROP INDEX courses_school_department_level_semester_code_unique');

        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('level_id')->nullable()->change();
            $table->foreignId('semester_id')->nullable()->change();
            $table->unique(['school_id', 'department_id', 'code'], 'courses_school_department_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique('courses_school_department_code_unique');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('level_id')->nullable(false)->change();
            $table->foreignId('semester_id')->nullable(false)->change();
            $table->unique(
                ['school_id', 'department_id', 'level_id', 'semester_id', 'code'],
                'courses_school_department_level_semester_code_unique',
            );
        });
    }
};
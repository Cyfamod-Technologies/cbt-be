<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email')) {
                return;
            }

            if (! Schema::hasColumn('users', 'matric_no')) {
                $table->string('matric_no')->nullable()->after('name');
                $table->unique(['school_id', 'matric_no']);
            }

            if (! Schema::hasColumn('users', 'student_id_no')) {
                $table->string('student_id_no')->nullable()->after('matric_no');
                $table->unique(['school_id', 'student_id_no']);
            }

            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('student_id_no')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'level_id')) {
                $table->foreignId('level_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('level_id');
            }

            $table->index(['department_id', 'level_id']);
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }

            if (Schema::hasColumn('users', 'level_id')) {
                $table->dropConstrainedForeignId('level_id');
            }

            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }

            if (Schema::hasColumn('users', 'student_id_no')) {
                $table->dropUnique(['school_id', 'student_id_no']);
                $table->dropColumn('student_id_no');
            }

            if (Schema::hasColumn('users', 'matric_no')) {
                $table->dropUnique(['school_id', 'matric_no']);
                $table->dropColumn('matric_no');
            }

            $table->string('email')->nullable(false)->change();
        });
    }
};

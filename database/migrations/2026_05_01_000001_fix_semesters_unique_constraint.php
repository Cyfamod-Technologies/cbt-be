<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            $table->dropUnique('semesters_school_id_name_unique');
            $table->unique(['school_id', 'session_id', 'name'], 'semesters_school_id_session_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            $table->dropUnique('semesters_school_id_session_id_name_unique');
            $table->unique(['school_id', 'name'], 'semesters_school_id_name_unique');
        });
    }
};

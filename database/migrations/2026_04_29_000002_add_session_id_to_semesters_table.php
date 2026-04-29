<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            if (! Schema::hasColumn('semesters', 'session_id')) {
                $table->foreignId('session_id')->nullable()->after('school_id')->constrained('sessions')->nullOnDelete();
                $table->index(['school_id', 'session_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            if (Schema::hasColumn('semesters', 'session_id')) {
                $table->dropConstrainedForeignId('session_id');
            }
        });
    }
};
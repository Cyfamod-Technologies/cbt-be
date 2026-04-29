<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 20)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'employment_start_date')) {
                $table->date('employment_start_date')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address', 255)->nullable()->after('employment_start_date');
            }

            if (! Schema::hasColumn('users', 'qualifications')) {
                $table->string('qualifications', 255)->nullable()->after('address');
            }

            if (! Schema::hasColumn('users', 'photo_url')) {
                $table->string('photo_url')->nullable()->after('qualifications');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'photo_url')) {
                $table->dropColumn('photo_url');
            }

            if (Schema::hasColumn('users', 'qualifications')) {
                $table->dropColumn('qualifications');
            }

            if (Schema::hasColumn('users', 'address')) {
                $table->dropColumn('address');
            }

            if (Schema::hasColumn('users', 'employment_start_date')) {
                $table->dropColumn('employment_start_date');
            }

            if (Schema::hasColumn('users', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('student')->after('password');
            $table->string('status', 30)->default('active')->after('role');
            $table->timestamp('last_login_at')->nullable()->after('status');

            $table->unique(['school_id', 'email']);
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'email']);
            $table->dropConstrainedForeignId('school_id');
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'last_login_at']);
        });

        Schema::dropIfExists('schools');
    }
};

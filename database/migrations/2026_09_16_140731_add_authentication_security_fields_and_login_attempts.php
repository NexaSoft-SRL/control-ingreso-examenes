<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')
                ->default(true);

            $table->unsignedInteger('failed_login_attempts')
                ->default(0);

            $table->timestamp('locked_until')
                ->nullable();

            $table->timestamp('last_login_at')
                ->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE users
                 ADD CONSTRAINT users_failed_login_attempts_non_negative
                 CHECK (failed_login_attempts >= 0)'
            );
        }

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('identifier');

            $table->boolean('successful')
                ->default(false);

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamp('attempted_at')
                ->useCurrent();

            $table->index(
                ['identifier', 'attempted_at'],
                'login_attempts_identifier_attempted_at_idx'
            );

            $table->index(
                ['user_id', 'attempted_at'],
                'login_attempts_user_attempted_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE users
                 DROP CONSTRAINT IF EXISTS users_failed_login_attempts_non_negative'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
            ]);
        });
    }
};

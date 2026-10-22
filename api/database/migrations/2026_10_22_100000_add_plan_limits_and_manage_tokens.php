<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->default('free')->after('timezone');
            $table->timestamp('suspended_at')->nullable()->after('plan');
        });

        DB::table('tenants')->update(['plan' => 'pro']);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('tenant_id');
        });

        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('manage_token_hash', 64)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('manage_token_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'suspended_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('users')->orderBy('id')->lazy()->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'public_id' => (string) Str::ulid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('public_id');
        });

        Schema::dropIfExists('tenants');
    }
};

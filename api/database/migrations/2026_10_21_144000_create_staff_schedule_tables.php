<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->index(['tenant_id', 'staff_profile_id', 'weekday'], 'staff_working_hours_lookup_index');
        });

        Schema::create('staff_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['tenant_id', 'staff_profile_id', 'starts_at'], 'staff_exceptions_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedule_exceptions');
        Schema::dropIfExists('staff_working_hours');
    }
};

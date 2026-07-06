<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('department')->default('general');
            $table->unsignedTinyInteger('seniority_min_years')->default(0);
            $table->unsignedTinyInteger('max_consecutive_days')->default(10);
            $table->boolean('allow_half_day')->default(true);
            $table->unsignedTinyInteger('required_approval_level')->default(2);
            $table->date('blackout_start_date')->nullable();
            $table->date('blackout_end_date')->nullable();
            $table->timestamps();

            $table->index(['department', 'seniority_min_years']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
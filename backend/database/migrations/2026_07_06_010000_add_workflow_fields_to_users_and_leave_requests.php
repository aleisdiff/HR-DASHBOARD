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
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('carry_over_leave_days')->default(0)->after('available_leave_days');
            $table->unsignedTinyInteger('approval_level')->default(0)->after('carry_over_leave_days');
            $table->string('department')->default('general')->after('approval_level');
            $table->unsignedTinyInteger('seniority_years')->default(0)->after('department');
        });

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->enum('leave_type', ['full_day', 'half_day'])->default('full_day')->after('status');
            $table->decimal('total_days', 5, 1)->default(0)->after('leave_type');
            $table->unsignedTinyInteger('current_approval_level')->default(0)->after('total_days');
            $table->unsignedTinyInteger('required_approval_level')->default(1)->after('current_approval_level');
            $table->foreignId('processed_by')->nullable()->after('required_approval_level')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('processed_by');
            $table->text('admin_note')->nullable()->after('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn([
                'leave_type',
                'total_days',
                'current_approval_level',
                'required_approval_level',
                'processed_at',
                'admin_note',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'carry_over_leave_days',
                'approval_level',
                'department',
                'seniority_years',
            ]);
        });
    }
};
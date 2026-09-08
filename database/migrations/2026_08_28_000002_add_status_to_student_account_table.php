<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Graduating students are deactivated rather than deleted: end-of-year
     * promotion marks anyone leaving 4th year 'inactive', which blocks sign-in
     * while keeping their clearance history intact for records requests.
     */
    public function up(): void
    {
        if (Schema::hasColumn('student_account', 'status')) {
            return;
        }

        Schema::table('student_account', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->after('student_type');
            $table->timestamp('deactivated_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('student_account', 'status')) {
            return;
        }

        Schema::table('student_account', function (Blueprint $table) {
            $table->dropColumn(['status', 'deactivated_at']);
        });
    }
};

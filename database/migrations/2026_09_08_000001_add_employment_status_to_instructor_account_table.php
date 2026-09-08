<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instructors are hired either as regular (full-time) faculty or as part
     * timers, and the office needs to tell the two apart from the account list.
     * Existing rows default to 'Regular' — that is what the college had before
     * anyone was recorded as part time.
     */
    public function up(): void
    {
        if (Schema::hasColumn('instructor_account', 'employment_status')) {
            return;
        }

        Schema::table('instructor_account', function (Blueprint $table) {
            $table->enum('employment_status', ['Regular', 'Part Timer'])->default('Regular')->after('department');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('instructor_account', 'employment_status')) {
            return;
        }

        Schema::table('instructor_account', function (Blueprint $table) {
            $table->dropColumn('employment_status');
        });
    }
};

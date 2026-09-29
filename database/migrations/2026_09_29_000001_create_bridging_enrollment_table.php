<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridging_enrollment', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id', 50);
            $table->timestamp('enrolled_at')->useCurrent();

            $table->unique(['student_id', 'subject_id', 'instructor_id'], 'unique_bridging_enrollment');
            $table->index('student_id', 'bridging_enrollment_student_idx');

            $table->foreign('student_id')->references('student_id')->on('student_account')->onDelete('cascade');
            $table->foreign('subject_id')->references('subject_id')->on('subject_codes')->onDelete('cascade');
            $table->foreign('instructor_id')->references('instructor_id')->on('instructor_account')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridging_enrollment');
    }
};

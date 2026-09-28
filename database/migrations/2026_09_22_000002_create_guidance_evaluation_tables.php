<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_evaluations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('questions');
            $table->json('sections')->nullable();
            $table->string('created_by', 50);
            $table->timestamp('published_at', 6)->nullable()->index();
            $table->unsignedTinyInteger('singleton')->nullable()->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });

        Schema::create('guidance_evaluation_responses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->string('student_id', 50)->index();
            $table->json('ratings');
            $table->timestamp('completed_at');
            $table->unique(['evaluation_id', 'student_id'], 'guidance_evaluation_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_evaluation_responses');
        Schema::dropIfExists('guidance_evaluations');
    }
};

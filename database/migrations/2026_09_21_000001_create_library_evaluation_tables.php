<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_evaluations')) {
            Schema::create('library_evaluations', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->json('questions');
                $table->string('created_by', 50);
                $table->timestamp('published_at', 6)->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('library_evaluation_responses')) {
            Schema::create('library_evaluation_responses', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('evaluation_id');
                $table->string('student_id', 50)->index();
                $table->json('ratings');
                $table->timestamp('completed_at');
                $table->unique(['evaluation_id', 'student_id'], 'library_evaluation_student_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_evaluation_responses');
        Schema::dropIfExists('library_evaluations');
    }
};

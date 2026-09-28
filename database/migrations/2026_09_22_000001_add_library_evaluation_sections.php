<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_evaluations', function (Blueprint $table) {
            $table->json('sections')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_evaluations', fn (Blueprint $table) => $table->dropColumn('sections'));
    }
};

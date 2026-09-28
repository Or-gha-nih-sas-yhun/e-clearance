<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_evaluations', function (Blueprint $table) {
            $table->unsignedTinyInteger('singleton')->nullable()->unique();
            $table->unsignedInteger('revision')->default(1);
        });

        // Keep the current form and its answers. Legacy forms remain inactive;
        // upgrading must not delete previously collected responses.
        $id = DB::table('library_evaluations')->orderByDesc('published_at')->orderByDesc('id')->value('id');
        if ($id !== null) {
            DB::table('library_evaluations')->where('id', $id)->update(['singleton' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('library_evaluations', function (Blueprint $table) {
            $table->dropUnique(['singleton']);
            $table->dropColumn(['singleton', 'revision']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The roster of Microsoft accounts allowed to self-register a student
     * portal account. A row starts 'inactive' and flips to 'active' the moment
     * its owner completes registration, which is what stops a second account
     * ever being created from the same entry.
     */
    public function up(): void
    {
        Schema::create('student_registry', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50)->unique();
            $table->string('ms_account', 150)->unique();
            $table->enum('status', ['inactive', 'active'])->default('inactive');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('status', 'stud   ent_registry_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registry');
    }
};

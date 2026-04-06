<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16);
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('status')->default('pending');
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_success')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};

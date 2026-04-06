<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->json('data');
            $table->timestamps();

            $table->index(['import_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};

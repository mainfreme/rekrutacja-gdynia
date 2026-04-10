<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id()->autoIncrement()->primaryKey();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_id');
            $table->text('error_message')->nullable();
            $table->dateTime('created_at');

            $table->index('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};

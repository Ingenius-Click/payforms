<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payforms_data', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('payform_id')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->json('currencies')->nullable();
            $table->json('args')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payforms_data');
    }
};

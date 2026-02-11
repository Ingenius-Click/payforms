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
        Schema::table('payforms_data', function (Blueprint $table) {
            $table->integer('expiration_hours')->nullable()->default(12)->after('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payforms_data', function (Blueprint $table) {
            $table->dropColumn('expiration_hours');
        });
    }
};

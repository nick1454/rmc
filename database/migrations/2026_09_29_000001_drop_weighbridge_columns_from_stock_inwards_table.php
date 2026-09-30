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
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropColumn(['gross_weight', 'tare_weight', 'net_weight']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->decimal('gross_weight', 12, 4)->nullable();
            $table->decimal('tare_weight', 12, 4)->nullable();
            $table->decimal('net_weight', 12, 4)->nullable();
        });
    }
};

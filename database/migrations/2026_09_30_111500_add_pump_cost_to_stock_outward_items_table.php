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
        Schema::table('stock_outward_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_outward_items', 'pump_cost')) {
                $table->decimal('pump_cost', 14, 2)->default(0)->after('rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outward_items', function (Blueprint $table) {
            $table->dropColumn('pump_cost');
        });
    }
};

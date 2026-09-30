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
        Schema::table('stock_outwards', function (Blueprint $table) {
            $table->decimal('paid_amount', 14, 2)->default(0)->after('total_amount');
            $table->string('payment_status', 30)->default('unpaid')->after('paid_amount'); // unpaid, partially_paid, paid
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outwards', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'payment_status']);
        });
    }
};

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
            $table->string('supplier_bill_no')->nullable()->after('challan_date');
            $table->date('supplier_bill_date')->nullable()->after('supplier_bill_no');
            $table->decimal('subtotal_amount', 14, 2)->default(0.00)->after('remarks');
            $table->decimal('tax_amount', 14, 2)->default(0.00)->after('subtotal_amount');
            $table->decimal('freight_amount', 14, 2)->default(0.00)->after('tax_amount');
            $table->decimal('other_charges', 14, 2)->default(0.00)->after('freight_amount');
            $table->decimal('discount_amount', 14, 2)->default(0.00)->after('other_charges');
            $table->decimal('grand_total', 14, 2)->default(0.00)->after('discount_amount');
        });

        Schema::table('stock_inward_items', function (Blueprint $table) {
            $table->decimal('rate_per_unit', 12, 2)->default(0.00)->after('conversion_factor');
            $table->decimal('taxable_amount', 14, 2)->default(0.00)->after('rate_per_unit');
            $table->decimal('gst_rate', 5, 2)->default(0.00)->after('taxable_amount');
            $table->decimal('gst_amount', 14, 2)->default(0.00)->after('gst_rate');
            $table->decimal('total_amount', 14, 2)->default(0.00)->after('gst_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_bill_no',
                'supplier_bill_date',
                'subtotal_amount',
                'tax_amount',
                'freight_amount',
                'other_charges',
                'discount_amount',
                'grand_total',
            ]);
        });

        Schema::table('stock_inward_items', function (Blueprint $table) {
            $table->dropColumn([
                'rate_per_unit',
                'taxable_amount',
                'gst_rate',
                'gst_amount',
                'total_amount',
            ]);
        });
    }
};

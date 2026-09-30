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
        Schema::create('stock_outwards', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no', 50)->unique(); // e.g. OUT-0001
            $table->string('entry_type', 30)->default('invoice'); // invoice, challan, transfer, return, sample
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('customer')->nullable(); // Customer or Recipient Name
            $table->foreignId('agent_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('agent_name')->nullable();
            $table->date('invoice_date'); // Outward / dispatch date
            $table->string('challan_no', 100)->nullable();
            $table->date('challan_date')->nullable();
            $table->string('vehicle_no', 50)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('destination')->nullable(); // Site / Project Yard
            $table->string('plant_name', 100)->nullable(); // Dispatch Plant
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('freight_charges', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('stock_outward_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_outward_id')->constrained('stock_outwards')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 12, 4)->default(0); // Primary Qty (e.g. CBM or Ton)
            $table->string('unit', 20)->default('cbm');
            $table->decimal('secondary_qty', 12, 4)->default(0);
            $table->string('secondary_unit', 20)->default('kgs');
            $table->decimal('conversion_factor', 10, 4)->default(1.0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(18.0);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('lotno', 100)->nullable(); // Batch / Lot No
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_outward_items');
        Schema::dropIfExists('stock_outwards');
    }
};

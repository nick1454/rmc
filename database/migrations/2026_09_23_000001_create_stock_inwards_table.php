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
        Schema::create('stock_inwards', function (Blueprint $table) {
            $table->id();
            $table->string('inward_no')->unique(); // e.g. INW-0001
            $table->date('inward_date');
            $table->foreignId('supplier_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('challan_no')->nullable();
            $table->date('challan_date')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('gross_weight', 12, 4)->nullable();
            $table->decimal('tare_weight', 12, 4)->nullable();
            $table->decimal('net_weight', 12, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_inward_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_inward_id')->constrained('stock_inwards')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty_primary', 12, 4)->default(0);
            $table->string('unit_primary')->default('ton');
            $table->decimal('qty_secondary', 12, 4)->default(0);
            $table->string('unit_secondary')->default('kgs');
            $table->decimal('conversion_factor', 10, 4)->default(1.0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_inward_items');
        Schema::dropIfExists('stock_inwards');
    }
};

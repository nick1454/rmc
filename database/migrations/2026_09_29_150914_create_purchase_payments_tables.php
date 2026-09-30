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
        // Add paid_amount and payment_status to stock_inwards
        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->decimal('paid_amount', 14, 2)->default(0.00)->after('grand_total');
            $table->string('payment_status', 20)->default('unpaid')->index()->after('paid_amount');
        });

        // Create purchase_payments table
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->date('payment_date');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('party_name')->nullable();
            $table->string('payment_mode')->default('bank_transfer'); // bank_transfer, cash, cheque, upi, neft_rtgs, other
            $table->string('reference_no')->nullable(); // UTR / Cheque / Txn ID
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 14, 2)->default(0.00);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // Create purchase_payment_items allocation table
        Schema::create('purchase_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->foreignId('stock_inward_id')->constrained('stock_inwards')->cascadeOnDelete();
            $table->decimal('invoice_amount', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_items');
        Schema::dropIfExists('purchase_payments');

        Schema::table('stock_inwards', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'payment_status']);
        });
    }
};

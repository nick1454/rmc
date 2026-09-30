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
        Schema::create('outward_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 50)->unique(); // e.g. REC-0001
            $table->date('payment_date');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('party_name')->nullable(); // Customer / Client Name
            $table->string('payment_mode', 50)->default('bank_transfer'); // bank_transfer, cheque, upi, cash, other
            $table->string('reference_no', 100)->nullable(); // UTR, Cheque #, Txn Ref
            $table->string('bank_name', 100)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('outward_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outward_payment_id')->constrained('outward_payments')->cascadeOnDelete();
            $table->foreignId('stock_outward_id')->constrained('stock_outwards')->cascadeOnDelete();
            $table->decimal('invoice_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outward_payment_items');
        Schema::dropIfExists('outward_payments');
    }
};

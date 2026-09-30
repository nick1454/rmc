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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // Aggregate, Cement, Dust, AddMixture, Silo Pouch
            $table->string('size')->nullable(); // 10mm, 20mm, 30mm, opc40, etc.
            $table->string('secondary_unit'); // kgs, pcs, etc.
            $table->string('primary_unit'); // ton, pcs, etc.
            $table->decimal('conversion_factor', 12, 4)->default(1.0000); // 1 primary_unit = X secondary_unit
            $table->string('type')->default('rm'); // rm, fg
            $table->string('hsn')->nullable(); // HSN
            $table->decimal('gst_per', 5, 2)->default(0.00); // gstper

            // Audit & Tracking Columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

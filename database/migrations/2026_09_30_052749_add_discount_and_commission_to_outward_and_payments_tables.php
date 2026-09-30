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
        // 1. stock_outwards (header)
        Schema::table('stock_outwards', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_outwards', 'commission_amount')) {
                $table->decimal('commission_amount', 14, 2)->default(0)->after('discount_amount');
            }
        });

        // 2. stock_outward_items (line items)
        Schema::table('stock_outward_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_outward_items', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('rate');
            }
            if (! Schema::hasColumn('stock_outward_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_percent');
            }
            if (! Schema::hasColumn('stock_outward_items', 'commission_agent_id')) {
                $table->foreignId('commission_agent_id')->nullable()->after('total_amount')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_outward_items', 'commission_agent_name')) {
                $table->string('commission_agent_name')->nullable()->after('commission_agent_id');
            }
            if (! Schema::hasColumn('stock_outward_items', 'commission_rate')) {
                $table->decimal('commission_rate', 10, 2)->default(0)->after('commission_agent_name');
            }
            if (! Schema::hasColumn('stock_outward_items', 'commission_amount')) {
                $table->decimal('commission_amount', 14, 2)->default(0)->after('commission_rate');
            }
        });

        // 3. outward_payments (header)
        Schema::table('outward_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('outward_payments', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('bank_name');
            }
            if (! Schema::hasColumn('outward_payments', 'commission_agent_id')) {
                $table->foreignId('commission_agent_id')->nullable()->after('discount_amount')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('outward_payments', 'commission_agent_name')) {
                $table->string('commission_agent_name')->nullable()->after('commission_agent_id');
            }
            if (! Schema::hasColumn('outward_payments', 'commission_amount')) {
                $table->decimal('commission_amount', 14, 2)->default(0)->after('commission_agent_name');
            }
        });

        // 4. outward_payment_items (line items)
        Schema::table('outward_payment_items', function (Blueprint $table) {
            if (! Schema::hasColumn('outward_payment_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('paid_amount');
            }
            if (! Schema::hasColumn('outward_payment_items', 'commission_agent_id')) {
                $table->foreignId('commission_agent_id')->nullable()->after('discount_amount')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('outward_payment_items', 'commission_agent_name')) {
                $table->string('commission_agent_name')->nullable()->after('commission_agent_id');
            }
            if (! Schema::hasColumn('outward_payment_items', 'commission_amount')) {
                $table->decimal('commission_amount', 14, 2)->default(0)->after('commission_agent_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outward_payment_items', function (Blueprint $table) {
            $table->dropForeign(['commission_agent_id']);
            $table->dropColumn([
                'discount_amount',
                'commission_agent_id',
                'commission_agent_name',
                'commission_amount',
            ]);
        });

        Schema::table('outward_payments', function (Blueprint $table) {
            $table->dropForeign(['commission_agent_id']);
            $table->dropColumn([
                'discount_amount',
                'commission_agent_id',
                'commission_agent_name',
                'commission_amount',
            ]);
        });

        Schema::table('stock_outward_items', function (Blueprint $table) {
            $table->dropForeign(['commission_agent_id']);
            $table->dropColumn([
                'discount_percent',
                'discount_amount',
                'commission_agent_id',
                'commission_agent_name',
                'commission_rate',
                'commission_amount',
            ]);
        });

        Schema::table('stock_outwards', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
        });
    }
};

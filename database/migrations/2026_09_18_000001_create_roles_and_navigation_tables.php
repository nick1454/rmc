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
        // 1. Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 2. Add role_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
        });

        // 3. Navigation Categories table (e.g. Operations, Master Data, Quality Control, Finance, Administration)
        Schema::create('navigation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Navigation Items table (Category wise links)
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('navigation_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('path');
            $table->string('icon')->default('LayoutDashboard');
            $table->string('badge')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Role & Navigation Item permissions mapping
        Schema::create('role_navigation_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('navigation_item_id')->constrained('navigation_items')->cascadeOnDelete();
            $table->unique(['role_id', 'navigation_item_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_navigation_item');
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('navigation_categories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};

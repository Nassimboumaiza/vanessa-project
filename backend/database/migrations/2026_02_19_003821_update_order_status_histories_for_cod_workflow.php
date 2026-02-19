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
        // Add previous_status column for tracking status transitions
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->string('previous_status', 50)->nullable()->after('order_id');
        });

        // Update status column to support COD workflow statuses
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->string('status', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->dropColumn('previous_status');
        });
    }
};

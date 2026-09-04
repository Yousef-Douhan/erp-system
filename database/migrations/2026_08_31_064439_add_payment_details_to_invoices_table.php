<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('payment_method')->default('cash')->after('customer_name');
            $table->string('wallet_phone')->nullable()->after('payment_method');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'payment_method', 'wallet_phone']);
        });
    }
};

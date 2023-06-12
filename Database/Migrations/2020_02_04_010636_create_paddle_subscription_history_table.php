<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('paddle_subscription_history')){
            return;
        }
        Schema::create('paddle_subscription_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger("order_id");
            $table->unsignedBigInteger("subscription_id");
            $table->unsignedBigInteger("user_id");
            $table->string("checkout_id")->nullable();
            $table->string("subscription_payment_id")->nullable();
            $table->string("subscription_plan_id")->nullable();
            $table->string("paddle_user_id")->nullable();
            $table->string("status")->default(1)->comment('1=active,0=cancel');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paddle_subscription_history');
    }
};

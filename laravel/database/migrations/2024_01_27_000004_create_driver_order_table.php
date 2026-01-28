<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_order', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('order_id');
            $table->string('status')->default('assigned'); // assigned, in_transit, delivered, cancelled
            $table->timestamps();
            
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            // Ensure unique assignment (one order to one driver at a time)
            $table->unique(['order_id', 'driver_id']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('driver_order');
    }
};
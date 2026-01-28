<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->string('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('status')->default('pending'); // pending, assigned, completed, cancelled
            $table->timestamps();
            
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
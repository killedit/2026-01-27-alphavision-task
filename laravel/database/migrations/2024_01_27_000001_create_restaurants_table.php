<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->integer('orders_count')->default(0);
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('restaurants');
    }
};
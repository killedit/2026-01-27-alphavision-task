<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('drivers')->truncate();

        for ($i = 1; $i <= 100; $i++) {
            DB::table('drivers')->insert([
                'id' => $i,
                'name' => 'Driver ' . $i,
                'lat' => 0,
                'lng' => 0,
                'capacity' => rand(1, 4),
                'next_restaurant_id' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}

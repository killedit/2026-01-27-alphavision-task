<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('restaurants')->truncate();

        $restaurants = [
            ['ХепиБъкстон', '42.667122', '23.281657'],
            ['ХепиВиктория', '42.688600', '23.308027'],
            ['ХепиСаутПарк', '42.670071', '23.313399'],
            ['ХепиБудапеща', '42.692017', '23.326259'],
            ['ХепиМолСофия', '42.6982608', '23.3078595'],
            ['ХепиМладост', '42.6481687', '23.3793724'],
            ['ХепиСветаНеделя', '42.696606', '23.3204766'],
            ['ХепиЛюлин', '42.713895', '23.264476'],
            ['ХепиПарадайс', '42.6570524', '23.3142243'],
            ['HappyИзток', '42.673136', '23.348732']
        ];

        foreach ($restaurants as $index => $restaurant) {
            DB::table('restaurants')->insert([
                'id' => $index + 1,
                'title' => $restaurant[0],
                'lat' => $restaurant[1],
                'lng' => $restaurant[2],
                'orders_count' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}

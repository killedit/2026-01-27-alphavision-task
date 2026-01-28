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
            ['Бъкстон', '42.667122', '23.281657'],
            ['Виктория', '42.688600', '23.308027'],
            ['Саут Парк', '42.670071', '23.313399'],
            ['Будапеща', '42.692017', '23.326259'],
            ['Мол София', '42.6982608', '23.3078595'],
            ['Младост', '42.6481687', '23.3793724'],
            ['Света Неделя', '42.696606', '23.3204766'],
            ['Люлин', '42.713895', '23.264476'],
            ['Парадайс', '42.6570524', '23.3142243'],
            ['Изток', '42.673136', '23.348732']
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

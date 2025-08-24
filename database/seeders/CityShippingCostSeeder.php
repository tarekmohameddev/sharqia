<?php

namespace Database\Seeders;

use App\Models\CityShippingCost;
use App\Models\Governorate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CityShippingCostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $governorates = Governorate::all();
        
        foreach ($governorates as $governorate) {
            CityShippingCost::updateOrCreate(
                ['governorate_id' => $governorate->id],
                ['cost' => 10.00] // Default shipping cost
            );
        }
    }
} 
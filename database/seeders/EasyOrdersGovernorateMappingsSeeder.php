<?php

namespace Database\Seeders;

use App\Models\EasyOrdersGovernorateMapping;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class EasyOrdersGovernorateMappingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds all existing governorates into the easy_orders_governorate_mappings table.
     * Uses the governorate's name_ar as the default easyorders_name.
     * Administrators can adjust mappings later via the admin UI if EasyOrders uses different names.
     */
    public function run(): void
    {
        $governorates = Governorate::all();

        foreach ($governorates as $governorate) {
            EasyOrdersGovernorateMapping::updateOrCreate(
                ['governorate_id' => $governorate->id],
                ['easyorders_name' => $governorate->name_ar]
            );
        }

        $this->command->info('EasyOrders governorate mappings seeded successfully. Total: ' . $governorates->count());
    }
}


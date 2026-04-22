<?php

namespace Database\Seeders;

use App\Models\Component;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComponentSampleSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = 1;
        $userId = 3;

        $samples = [
            [
                'name' => 'Nozzle Cutting',
                'type_size' => '1.2 mm',
                'weight' => 0.10,
                'component_category_id' => 1,
                'price_buy' => 45000,
                'price_sell' => 65000,
                'qty' => 24,
            ],
            [
                'name' => 'Baut Hex',
                'type_size' => 'M10 x 30',
                'weight' => 0.05,
                'component_category_id' => 2,
                'price_buy' => 2500,
                'price_sell' => 4000,
                'qty' => 150,
            ],
            [
                'name' => 'Bearing Linear',
                'type_size' => 'LM20UU',
                'weight' => 0.35,
                'component_category_id' => 4,
                'price_buy' => 42000,
                'price_sell' => 60000,
                'qty' => 18,
            ],
            [
                'name' => 'Contactor',
                'type_size' => '220V 18A',
                'weight' => 0.40,
                'component_category_id' => 5,
                'price_buy' => 78000,
                'price_sell' => 110000,
                'qty' => 12,
            ],
        ];

        DB::transaction(function () use ($samples, $branchId, $userId) {
            foreach ($samples as $sample) {
                $component = Component::withTrashed()->updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'name' => $sample['name'],
                        'type_size' => $sample['type_size'],
                    ],
                    [
                        'weight' => $sample['weight'],
                        'supplier_id' => null,
                        'component_category_id' => $sample['component_category_id'],
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]
                );

                DB::table('component_price_histories')
                    ->where('component_id', $component->id)
                    ->delete();

                DB::table('component_stock_movements')
                    ->where('component_id', $component->id)
                    ->delete();

                DB::table('component_price_histories')->insert([
                    [
                        'component_id' => $component->id,
                        'old_price' => null,
                        'new_price' => $sample['price_buy'],
                        'type' => 'BUY',
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'component_id' => $component->id,
                        'old_price' => null,
                        'new_price' => $sample['price_sell'],
                        'type' => 'SELL',
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

                DB::table('component_stock_movements')->insert([
                    'component_id' => $component->id,
                    'qty' => $sample['qty'],
                    'type' => 'IN',
                    'description' => 'Seeder sample component stock',
                    'reference_id' => 'component-sample-seeder',
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}

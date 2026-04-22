<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customers')->insert([
            [
                'full_name' => 'PT Maju Jaya Abadi',
                'contact_name' => 'Budi Santoso',
                'phone_number' => '081234567890',
                'address' => 'Surabaya',
                'branch_id' => 1,
                'sales_id' => 1, // pastikan user sales ada
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'CV Sukses Selalu',
                'contact_name' => 'Andi Wijaya',
                'phone_number' => '082233445566',
                'address' => 'Malang',
                'branch_id' => 1,
                'sales_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'UD Berkah Logam',
                'contact_name' => 'Siti Aminah',
                'phone_number' => '085678901234',
                'address' => 'Mojokerto',
                'branch_id' => 2,
                'sales_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'PT Sinar Logam',
                'contact_name' => 'Ahmad',
                'phone_number' => '081234567001',
                'address' => 'Surabaya',
                'branch_id' => 1,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'CV Baja Perkasa',
                'contact_name' => 'Rudi',
                'phone_number' => '081234567002',
                'address' => 'Malang',
                'branch_id' => 1,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'UD Karya Mandiri',
                'contact_name' => 'Siti',
                'phone_number' => '081234567003',
                'address' => 'Mojokerto',
                'branch_id' => 2,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'PT Mega Teknik',
                'contact_name' => 'Dewi',
                'phone_number' => '081234567004',
                'address' => 'Sidoarjo',
                'branch_id' => 1,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'CV Prima Steel',
                'contact_name' => 'Joko',
                'phone_number' => '081234567005',
                'address' => 'Gresik',
                'branch_id' => 1,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'UD Sumber Rejeki',
                'contact_name' => 'Wawan',
                'phone_number' => '081234567006',
                'address' => 'Pasuruan',
                'branch_id' => 2,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'PT Anugerah Las',
                'contact_name' => 'Rina',
                'phone_number' => '081234567007',
                'address' => 'Probolinggo',
                'branch_id' => 2,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'CV Teknik Jaya',
                'contact_name' => 'Fajar',
                'phone_number' => '081234567008',
                'address' => 'Blitar',
                'branch_id' => 2,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'UD Logam Sentosa',
                'contact_name' => 'Hendra',
                'phone_number' => '081234567009',
                'address' => 'Kediri',
                'branch_id' => 2,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'PT Baja Nusantara',
                'contact_name' => 'Agus',
                'phone_number' => '081234567010',
                'address' => 'Tulungagung',
                'branch_id' => 1,
                'sales_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

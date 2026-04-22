<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->truncate();

        DB::table('users')->insert([
            [
                'id' => 1,
                'name' => 'Sales Surabaya',
                'email' => 'sales1@example.com',
                'email_verified_at' => null,
                'username' => 'sales_sby',
                'password' => '$2y$12$aXDRRqbvn/rTdLKTv961R.k.iDj6j32jHgIZF9mC0nGsWEXwJmnEW',
                'remember_token' => null,
                'role_id' => 6,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:05',
                'updated_at' => '2026-04-17 15:19:05',
            ],
            [
                'id' => 2,
                'name' => 'Sales Malang',
                'email' => 'sales2@example.com',
                'email_verified_at' => null,
                'username' => 'sales_mlg',
                'password' => '$2y$12$DCwiXjN54iHHMVT1Veak0evDwMuUw4p2O18eYnnBbGOEsp6PbNAoK',
                'remember_token' => null,
                'role_id' => 6,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:06',
                'updated_at' => '2026-04-17 15:19:06',
            ],
            [
                'id' => 3,
                'name' => 'Developer',
                'email' => 'developer@example.com',
                'email_verified_at' => '2026-04-17 15:19:06',
                'username' => 'admin',
                'password' => '$2y$12$s9xwQNb0mlreh69/1k18ueA1WWtkover4mDr/lIsVb8UgD4UaOr1i',
                'remember_token' => 'mP9o2fUWEg',
                'role_id' => 1,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:06',
                'updated_at' => '2026-04-17 16:47:15',
            ],
            [
                'id' => 4,
                'name' => 'Administrator',
                'email' => 'administrator@example.com',
                'email_verified_at' => '2026-04-17 15:19:06',
                'username' => 'administrator',
                'password' => '$2y$12$I.hYmVZXL0Z6P/Sg87ZCgOGCOnGhoGJk9mM/jKRAggZUKWQuCOXZG',
                'remember_token' => 'ZuhBxY5QsE',
                'role_id' => 2,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:07',
                'updated_at' => '2026-04-18 08:05:30',
            ],
            [
                'id' => 5,
                'name' => 'Admin Malang',
                'email' => 'malang@cabang.com',
                'email_verified_at' => '2026-04-17 15:19:07',
                'username' => 'malang',
                'password' => '$2y$12$GynbWWd4l0a8eB3a5hvkuuDiT6iVM8A2wzYC2tn.YcU/VyMMRNseG',
                'remember_token' => '1Tb1AbRdVt',
                'role_id' => 2,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:07',
                'updated_at' => '2026-04-18 08:06:14',
            ],
            [
                'id' => 6,
                'name' => 'Customer Support (CS)',
                'email' => 'cs@example.com',
                'email_verified_at' => '2026-04-17 15:19:07',
                'username' => 'support',
                'password' => '$2y$12$vspEzUQkhOqPlnj7KOJUoObkVtD3/MLpyOSscfYx/UptD8G0mVXJy',
                'remember_token' => '6jTHBZuuG5',
                'role_id' => 4,
                'branch_id' => 1,
                'deleted_at' => null,
                'deleted_by' => null,
                'created_at' => '2026-04-17 15:19:07',
                'updated_at' => '2026-04-18 08:06:55',
            ],
        ]);
    }
}

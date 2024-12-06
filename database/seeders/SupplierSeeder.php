<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $factory = new SupplierFactory();
        
        foreach(range(1, 10) as $index) {
            $data = $factory->definition();
            
            DB::table('suppliers')->insert([
                'name' => $data['name'],
                'contact_person' => $data['contact_person'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'notes' => $data['notes'],
                'active' => DB::raw($data['active'] ? 'true' : 'false'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
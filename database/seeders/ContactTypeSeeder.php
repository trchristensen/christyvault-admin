<?php

namespace Database\Seeders;

use App\Models\ContactType;
use Illuminate\Database\Seeder;

class ContactTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'sales',
            'delivery',
            'billing',
        ];

        foreach ($types as $type) {
            ContactType::create(['name' => $type]);
        }
    }
}

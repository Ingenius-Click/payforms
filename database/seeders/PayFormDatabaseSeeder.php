<?php

namespace Ingenius\Payforms\Database\Seeders;

use Illuminate\Database\Seeder;

class PayFormDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Payment forms are registered dynamically through the PayformsManager service
        // No initial seed data is required as payment methods are configured at runtime
        // $this->call([]);
    }
}

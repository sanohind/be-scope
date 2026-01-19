<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AsakaiTitle;

class AsakaiTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $titles = [
            // Safety Category
            ['title' => 'Safety - Fatal Accident', 'category' => 'Safety'],
            ['title' => 'Safety - LOST Working Day', 'category' => 'Safety'],
            
            // Quality Category
            ['title' => 'Quality - Customer Claim', 'category' => 'Quality'],
            ['title' => 'Quality - Warranty Claim', 'category' => 'Quality'],
            ['title' => 'Quality - Service Part', 'category' => 'Quality'],
            ['title' => 'Quality - Export Part', 'category' => 'Quality'],
            ['title' => 'Quality - Local Supplier', 'category' => 'Quality'],
            ['title' => 'Quality - Overseas Supplier', 'category' => 'Quality'],
            
            // Delivery Category
            ['title' => 'Delivery - Shortage', 'category' => 'Delivery'],
            ['title' => 'Delivery - Miss Part', 'category' => 'Delivery'],
            ['title' => 'Delivery - Line Stop', 'category' => 'Delivery'],
            ['title' => 'Delivery - On Time Delivery', 'category' => 'Delivery'],
            ['title' => 'Delivery - Criple', 'category' => 'Delivery'],
        ];

        foreach ($titles as $title) {
            AsakaiTitle::create($title);
        }
    }
}

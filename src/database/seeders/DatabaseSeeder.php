
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberPointLog;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create customers
        $customers = [];
        for ($i = 1; $i <= 5; $i++) {
            $customers[] = Customer::create([
                'name' => "Customer $i",
                'email' => "customer$i@example.com",
            ]);
        }
        
        // Create members for each customer
        $members = [];
        foreach ($customers as $customer) {
            for ($i = 1; $i <= 3; $i++) {
                $members[] = Member::create([
                    'name' => "Member {$customer->id}-$i",
                    'email' => "member-{$customer->id}-$i@example.com",
                    'customer_id' => $customer->id,
                ]);
            }
        }
        
        // Create point logs across multiple years
        $years = [2020, 2021, 2022, 2023, 2024];
        $types = ['purchase', 'reward', 'bonus', 'expiration'];
        
        foreach ($members as $member) {
            foreach ($years as $year) {
                // Create 10 point logs per member per year
                for ($i = 1; $i <= 10; $i++) {
                    $date = Carbon::create($year, rand(1, 12), rand(1, 28));
                    $type = $types[array_rand($types)];
                    $points = rand(10, 1000);
                    
                    MemberPointLog::create([
                        'member_id' => $member->id,
                        'customer_id' => $member->customer_id,
                        'points' => $type === 'expiration' ? -$points : $points,
                        'description' => ucfirst($type) . " points transaction #$i",
                        'type' => $type,
                        'created_at' => $date,
                    ]);
                }
            }
        }
    }
}

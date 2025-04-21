
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use Carbon\Carbon;

class CreatePointLogPartitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pointlog:create-partitions {year? : The year to create partitions for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create point log partitioned tables for all customers for a specific year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year') ?? Carbon::now()->year;
        
        $this->info("Creating partitioned tables for year: $year");
        
        $customers = Customer::all();
        $model = new \App\Models\MemberPointLog();
        
        $bar = $this->output->createProgressBar(count($customers));
        $bar->start();
        
        foreach ($customers as $customer) {
            $tableName = $model->getPartitionedTableName($customer->id, $year);
            
            // Check if table already exists
            if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                $this->line("\nTable $tableName already exists. Skipping...");
                $bar->advance();
                continue;
            }
            
            // Create the table
            $model->ensureTableExists($customer->id, $year);
            $this->line("\nCreated table: $tableName");
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->info("\nPartitioned tables created successfully!");
    }
}

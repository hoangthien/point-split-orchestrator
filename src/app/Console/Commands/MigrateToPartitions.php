
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MemberPointLogService;

class MigrateToPartitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pointlog:migrate-to-partitions {--original=member_point_log : Original table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from the original point log table to partitioned tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $originalTable = $this->option('original');
        
        $this->info("Starting migration from $originalTable to partitioned tables");
        $this->warn("This process might take a long time for large tables");
        
        if (!$this->confirm('Do you want to continue?', true)) {
            $this->info('Operation cancelled.');
            return;
        }
        
        $service = new MemberPointLogService();
        $this->info("Migrating data... This may take some time");
        
        $startTime = microtime(true);
        $result = $service->migrateDataToPartitions($originalTable);
        $endTime = microtime(true);
        
        if ($result['success']) {
            $this->info($result['message']);
            $this->info(sprintf("Migration completed in %.2f seconds", $endTime - $startTime));
        } else {
            $this->error($result['message']);
        }
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Carbon\Carbon;
use App\Traits\UsesPartitionedTables;

class MemberPointLog extends Model
{
    use HasFactory, UsesPartitionedTables;

    protected $fillable = [
        'member_id',
        'customer_id',
        'points',
        'description',
        'type',
    ];

    /**
     * Get the actual table name for this instance
     */
    public function getTable()
    {
        // If a table has been explicitly set, use it
        if (isset($this->table)) {
            return $this->table;
        }

        // If we're creating a new record and have customer_id and created_at
        if (!$this->exists && isset($this->attributes['customer_id'])) {
            $customerId = $this->attributes['customer_id'];
            $createdAt = $this->attributes['created_at'] ?? now();
            $year = Carbon::parse($createdAt)->year;
            return $this->getPartitionedTableName($customerId, $year);
        }

        // For existing records, just return the current table name
        // This shouldn't be called as we're not using the base table anymore
        return parent::getTable();
    }

    /**
     * Create a new record in the appropriate partitioned table
     */
    public static function create(array $attributes = [])
    {
        $model = new static($attributes);
        
        // Set created_at if not provided
        if (!isset($attributes['created_at'])) {
            $model->created_at = now();
        }
        
        // Ensure the appropriate table exists
        $model->ensureTableExists($attributes['customer_id'], Carbon::parse($model->created_at)->year);
        
        $model->save();
        
        return $model;
    }

    /**
     * Update an existing record
     */
    public function update(array $attributes = [], array $options = [])
    {
        // If customer_id or created_at is being updated, ensure record is in the right table
        if (isset($attributes['customer_id']) || isset($attributes['created_at'])) {
            $customerId = $attributes['customer_id'] ?? $this->customer_id;
            $createdAt = $attributes['created_at'] ?? $this->created_at;
            $year = Carbon::parse($createdAt)->year;
            
            $newTable = $this->getPartitionedTableName($customerId, $year);
            $currentTable = $this->getTable();
            
            // If table would change, move the record to the new table
            if ($newTable !== $currentTable) {
                // Ensure new table exists
                $this->ensureTableExists($customerId, $year);
                
                // Create a copy in the new table and delete the old one
                $attributes = array_merge($this->getAttributes(), $attributes);
                $newModel = (new static)->setTable($newTable)->create($attributes);
                
                // Delete the old record
                $this->delete();
                
                // Return the new model
                return $newModel;
            }
        }
        
        // Normal update
        return parent::update($attributes, $options);
    }

    /**
     * Generate the name for a partitioned table
     */
    public function getPartitionedTableName($customerId, $year)
    {
        return "member_point_log_{$customerId}_{$year}";
    }

    /**
     * Make sure the table exists before trying to use it
     */
    public function ensureTableExists($customerId, $year)
    {
        $tableName = $this->getPartitionedTableName($customerId, $year);
        
        if (!Schema::hasTable($tableName)) {
            $this->createPartitionTable($tableName);
        }
        
        return $tableName;
    }

    /**
     * Create a new partition table with the same structure
     */
    protected function createPartitionTable($tableName)
    {
        Schema::create($tableName, function ($table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('customer_id');
            $table->integer('points');
            $table->string('description')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
            
            // Add the same indexes as the main table
            $table->index('member_id');
            $table->index('customer_id');
            $table->index('created_at');
        });
        
        return $tableName;
    }

    /**
     * Query for a specific member across all relevant tables
     */
    public function forMember($memberId)
    {
        $member = Member::findOrFail($memberId);
        $customerId = $member->customer_id;
        
        return $this->queryMultipleTables($customerId);
    }

    /**
     * Query for a specific customer across all relevant tables
     */
    public function forCustomer($customerId)
    {
        return $this->queryMultipleTables($customerId);
    }

    /**
     * Query for specific date range across all relevant tables
     */
    public function forDateRange($startDate, $endDate)
    {
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // Get all customers
        $customers = Customer::pluck('id')->toArray();
        
        return $this->queryMultipleTables($customers, $startYear, $endYear);
    }

    /**
     * Query multiple tables based on criteria
     */
    protected function queryMultipleTables($customerIds, $startYear = null, $endYear = null)
    {
        // Convert single ID to array
        if (!is_array($customerIds)) {
            $customerIds = [$customerIds];
        }
        
        // Default to current year if no range specified
        if ($startYear === null) {
            $startYear = Carbon::now()->year;
        }
        
        if ($endYear === null) {
            $endYear = Carbon::now()->year;
        }
        
        $partitions = [];
        
        // Find all relevant tables
        foreach ($customerIds as $customerId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $this->getPartitionedTableName($customerId, $year);
                
                // Skip if table doesn't exist
                if (!Schema::hasTable($tableName)) {
                    continue;
                }
                
                $partitions[] = $tableName;
            }
        }
        
        // Use the trait method to query multiple partitions
        return $this->queryPartitions($partitions);
    }

    /**
     * Find a record across all partitions
     */
    public static function findAcrossPartitions($id, $customerId = null)
    {
        $instance = new static;
        $partitions = [];
        
        if ($customerId) {
            // If customer ID is known, only search its partitions
            $partitions = $instance->getCustomerPartitions($customerId);
        } else {
            // Get pattern for all member point log tables
            $partitions = $instance->getExistingPartitions('/^member_point_log_\d+_\d{4}$/');
        }
        
        if (empty($partitions)) {
            return null;
        }
        
        // Search across all relevant partitions
        $query = $instance->queryPartitions($partitions);
        return $query->where('id', $id)->first();
    }

    /**
     * Get relationship with Member
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get relationship with Customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

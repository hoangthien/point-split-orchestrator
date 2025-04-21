
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Models\MemberPointLog;

/**
 * @method static \Illuminate\Support\Collection forMember(int $memberId)
 * @method static \Illuminate\Support\Collection forCustomer(int $customerId)
 * @method static \Illuminate\Support\Collection forDateRange(string $startDate, string $endDate)
 * @method static \App\Models\MemberPointLog create(array $attributes)
 * @method static string getPartitionedTableName(int $customerId, int $year)
 * @method static string ensureTableExists(int $customerId, int $year)
 * @method static mixed findAcrossPartitions(int $id, int|null $customerId = null)
 * 
 * @see \App\Models\MemberPointLog
 */
class PointLog extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MemberPointLog::class;
    }
}

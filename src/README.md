
# Laravel Partitioned Point Logs

This project demonstrates how to implement table partitioning for large datasets in Laravel, specifically for a point log system where data grows significantly over time.

## Problem Statement

A large member_point_log table with data spanning multiple years (50GB+) is causing performance issues despite indexing. The solution is to partition the data by customer and year into separate tables (member_point_log_{customer_id}_{year}).

## Key Features

- Transparent table partitioning by customer ID and year
- MemberPointLog model that automatically routes operations to the correct table
- Support for standard Eloquent operations like create()
- Query capabilities across multiple partitioned tables
- Minimal code changes required to existing application

## How It Works

1. **Custom Model**: The MemberPointLog model determines the appropriate table name dynamically.
2. **Transparent Creation**: When creating a new record, it automatically selects or creates the correct partition.
3. **Query Builder**: Custom methods allow querying across multiple tables based on filters.

## Technical Implementation

### Key Components

- **MemberPointLog Model**: Handles dynamic table selection and creation
- **MemberPointLogService**: Service for complex operations across multiple tables
- **CreatePointLogPartitions Command**: CLI command to pre-create partition tables

### Database Design

- Each partition follows the same schema as the original table
- Naming convention: member_point_log_{customer_id}_{year}
- Each partition has its own indexes for optimal performance

## Usage Examples

### Creating a New Point Log

```php
MemberPointLog::create([
    'member_id' => 1,
    'customer_id' => 5,
    'points' => 100,
    'description' => 'Purchase reward',
    'type' => 'reward'
]);
```

### Querying Across Partitions

```php
// Get point logs for a specific member
$memberPointLog = new MemberPointLog();
$logs = $memberPointLog->forMember($memberId);

// Get point logs for a date range
$logs = $memberPointLog->forDateRange('2023-01-01', '2023-12-31');
```

## Performance Benefits

- Smaller individual tables lead to faster queries
- Indexes are more effective on smaller datasets
- Operations only affect relevant partitions instead of the entire dataset
- Maintenance operations can be performed on individual partitions

## Maintenance

- Use the `pointlog:create-partitions` command to pre-create partition tables for new years
- Consider archiving old partitions for very old data

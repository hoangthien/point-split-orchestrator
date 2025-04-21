
# Upgrading from Single Table to Partitioned Tables

This document describes how to migrate from a single `member_point_log` table to partitioned tables by customer ID and year.

## Migration Process

### 1. Install the New Code

Install the updated code which includes:
- Updated `MemberPointLog` model
- New migration files
- New commands for managing partitions
- Service providers and traits

### 2. Create Partition Tables

Run the command to create partition tables for existing years:

```bash
php artisan pointlog:create-partitions 2020
php artisan pointlog:create-partitions 2021
php artisan pointlog:create-partitions 2022
php artisan pointlog:create-partitions 2023
php artisan pointlog:create-partitions 2024
```

This will create the necessary partition tables for each customer and year.

### 3. Migrate Data

Migrate data from the original table to the partitioned tables:

```bash
php artisan pointlog:migrate-to-partitions
```

This command will:
- Process data in chunks to avoid memory issues
- Create partition tables as needed
- Copy data to the appropriate tables based on customer ID and creation date

### 4. Verify Data

Verify that all data has been properly migrated:

```bash
php artisan tinker
```

```php
// Check counts in original table
DB::table('member_point_log')->count();

// Check total in partitioned tables
$tables = DB::select("SHOW TABLES LIKE 'member_point_log_%'");
$total = 0;
foreach ($tables as $table) {
    $tableName = array_values((array) $table)[0];
    $count = DB::table($tableName)->count();
    echo "$tableName: $count\n";
    $total += $count;
}
echo "Total in partitioned tables: $total\n";
```

### 5. Update Applications

The MemberPointLog model has been designed to be backward compatible with existing code. Most basic operations should continue to work without changes.

However, you may need to update:

- Complex joins across the table
- Direct SQL queries that reference the table name
- Reports or analytics that aggregate across the entire dataset

### 6. Performance Monitoring

After migration, monitor query performance to ensure improvements:

```php
// Enable query logging
DB::enableQueryLog();

// Run your query
$logs = MemberPointLog::forCustomer(1)->whereBetween('created_at', ['2023-01-01', '2023-12-31'])->get();

// Check executed queries
dd(DB::getQueryLog());
```

### 7. Removal of Original Table (Optional)

Once you've verified all data has been migrated and applications are working correctly, you can consider:

1. Renaming the original table (for backup)
2. Creating a view with the original name that unions all partitioned tables (for backward compatibility)
3. Eventually dropping the original table when you're confident it's no longer needed

```sql
-- Rename original table
RENAME TABLE member_point_log TO member_point_log_original;

-- Create a view for backward compatibility (simplified example)
CREATE VIEW member_point_log AS
SELECT * FROM member_point_log_1_2023
UNION ALL SELECT * FROM member_point_log_1_2024
UNION ALL SELECT * FROM member_point_log_2_2023
...
```

## Rollback Plan

If you encounter issues, you can temporarily revert to the original table:

1. Ensure the original table still exists
2. Update the MemberPointLog model to temporarily use the original table:

```php
// In MemberPointLog model
protected $table = 'member_point_log_original';
```

This will bypass the dynamic table selection and use the original table.

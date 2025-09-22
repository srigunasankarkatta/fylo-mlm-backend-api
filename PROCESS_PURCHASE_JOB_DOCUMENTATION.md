# ProcessPurchaseJob - Complete Implementation Documentation

## Overview

The `ProcessPurchaseJob` is a production-ready Laravel job that handles the complete income distribution system for MLM package purchases. It implements **Level Income**, **Fasttrack Income**, and **Company Allocation** with proper bcmath precision, idempotency, and transactional safety.

## Features

### ✅ **Implemented Features**

1. **Level Income Distribution**
   - Fixed amount per ancestor in placement tree
   - Walks up parent chain until root
   - Configurable via `income_configs.metadata.fixed_amount`
   - Fallback to `income_configs.percentage` as fixed amount
   - Default: 0.5 units per level

2. **Fasttrack Income Distribution**
   - Percentage-based income to immediate upline
   - Package-specific or global configurations
   - Credits company wallet if no parent exists
   - Configurable percentages (supports both 10% and 0.10 formats)

3. **Company Allocation for AutoPool**
   - Dedicated company portion for AutoPool distribution
   - Configurable via `income_configs.metadata.company_share`
   - Credits `company_total` wallet

4. **Production-Ready Features**
   - **Idempotent**: Prevents duplicate processing
   - **Transactional**: Database consistency guaranteed
   - **bcmath Precision**: Accurate monetary calculations
   - **Row-level Locking**: Prevents race conditions
   - **Comprehensive Logging**: Full audit trail
   - **Error Handling**: Graceful failure and retry support

## Database Requirements

### Required Tables
- `user_packages` - Purchase orders
- `user_tree` - Placement tree structure
- `income_configs` - Income configuration
- `wallets` - User and company wallets
- `ledger_transactions` - Immutable transaction log
- `income_records` - Income distribution records

### Required Fields

**user_packages table:**
```sql
processing BOOLEAN DEFAULT FALSE
processed_at TIMESTAMP NULL
idempotency_key VARCHAR(100) UNIQUE
```

**income_records table:**
```sql
user_id BIGINT NULL  -- Made nullable for company allocations
income_type ENUM('level', 'fasttrack', 'club', 'autopool', 'company_allocation', 'other')
```

## Configuration

### Income Configs Setup

#### 1. Fasttrack Configuration
```php
IncomeConfig::create([
    'income_type' => 'fasttrack',
    'package_id' => 1, // Optional: package-specific
    'name' => 'Fasttrack 10%',
    'percentage' => 10, // 10% or 0.10 both work
    'is_active' => true,
    'metadata' => ['description' => '10% fasttrack for package 1']
]);
```

#### 2. Level Income Configuration
```php
IncomeConfig::create([
    'income_type' => 'level',
    'name' => 'Level Income',
    'percentage' => 0.5, // Fallback if metadata.fixed_amount not set
    'is_active' => true,
    'metadata' => [
        'fixed_amount' => 0.5, // Recommended: explicit fixed amount
        'description' => '0.5 per level'
    ]
]);
```

#### 3. Company Allocation Configuration
```php
IncomeConfig::create([
    'income_type' => 'fasttrack',
    'name' => 'Company Allocation',
    'percentage' => 5,
    'is_active' => true,
    'metadata' => [
        'company_share' => 5, // 5% company allocation
        'description' => '5% company allocation for AutoPool'
    ]
]);
```

## Usage

### Basic Usage
```php
use App\Jobs\ProcessPurchaseJob;

// Dispatch job for user package ID 123
ProcessPurchaseJob::dispatch(123);

// Or dispatch with delay
ProcessPurchaseJob::dispatch(123)->delay(now()->addMinutes(5));
```

### Integration with Purchase Flow
```php
// In PurchaseService::confirmPurchase()
DB::transaction(function () use ($order, $payload) {
    $order->update([
        'payment_status' => 'completed',
        'payment_reference' => $payload['payment_reference'],
        'purchase_at' => now()
    ]);

    // Dispatch processing job
    ProcessPurchaseJob::dispatch($order->id);
});
```

## Income Distribution Logic

### 1. Fasttrack Distribution
```
1. Find active fasttrack configs (package-specific first, then global)
2. For each config:
   - Calculate amount = order.amount_paid * percentage
   - Find immediate parent in user_tree
   - If parent exists: credit parent's fasttrack wallet
   - If no parent: credit company_total wallet
3. Create ledger_transaction and income_record
```

### 2. Level Income Distribution
```
1. Find level income config
2. Get fixed amount from metadata.fixed_amount or percentage field
3. Walk up placement tree:
   - For each ancestor: credit commission wallet
   - Continue until root (parent_id = null)
4. Create ledger_transaction and income_record for each level
```

### 3. Company Allocation
```
1. Find company allocation config (metadata.company_share)
2. Calculate amount = order.amount_paid * company_share
3. Credit company_total wallet
4. Create ledger_transaction and income_record
```

## Mathematical Precision

### bcmath Functions Used
- `bcmul($a, $b, $scale)` - Multiplication
- `bcadd($a, $b, $scale)` - Addition
- `bcdiv($a, $b, $scale)` - Division
- `bccomp($a, $b, $scale)` - Comparison

### Precision Settings
- **Scale**: 8 decimal places
- **Currency**: USD (configurable)
- **Amount Format**: Decimal strings for precision

### Percentage Normalization
```php
// Handles both formats:
// 10 => 0.10 (10%)
// 0.1 => 0.001 (0.1%)
// 0.10 => 0.10 (10%)
```

## Error Handling

### Idempotency Checks
- Checks `processed_at` timestamp
- Checks existing `income_records` for same order
- Prevents duplicate processing

### Transaction Safety
- All operations wrapped in DB transaction
- Row-level locking on wallet updates
- Automatic rollback on failure

### Error Recovery
- Reverts `processing` flag on failure
- Allows job retry according to queue settings
- Comprehensive error logging

## Testing

### Test Data Setup
```php
// Create user tree structure
$root = User::create(['name' => 'Root', 'email' => 'root@example.com']);
$rootTree = UserTree::create([
    'user_id' => $root->id,
    'parent_id' => null,
    'path' => '/',
    'depth' => 0
]);

$user = User::create(['name' => 'User', 'email' => 'user@example.com']);
$userTree = UserTree::create([
    'user_id' => $user->id,
    'parent_id' => $rootTree->id,
    'path' => '/' . $root->id . '/',
    'depth' => 1
]);

// Create income configs
IncomeConfig::create([
    'income_type' => 'fasttrack',
    'percentage' => 10,
    'is_active' => true
]);

IncomeConfig::create([
    'income_type' => 'level',
    'is_active' => true,
    'metadata' => ['fixed_amount' => 0.5]
]);
```

### Manual Testing
```php
// Test job execution
$job = new ProcessPurchaseJob($userPackageId);
$job->handle();

// Verify results
echo "Income Records: " . IncomeRecord::count();
echo "Ledger Transactions: " . LedgerTransaction::count();
echo "Wallets: " . Wallet::count();
```

## Queue Configuration

### Environment Setup
```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

### Queue Worker
```bash
# Start queue worker
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=default

# With timeout and memory limits
php artisan queue:work --timeout=300 --memory=512
```

### Job Monitoring
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## Performance Considerations

### Database Indexes
- `user_packages.idempotency_key` - Unique index
- `income_records.user_package_id` - Index for idempotency checks
- `user_tree.user_id` - Index for parent lookups
- `wallets.user_id,wallet_type,currency` - Composite index

### Memory Usage
- Processes one order at a time
- Uses database transactions for memory efficiency
- No large data loading

### Scalability
- Can be run in parallel (idempotent)
- Horizontal scaling via multiple queue workers
- Database partitioning possible for large datasets

## Security Features

### Data Integrity
- Immutable ledger transactions
- Row-level locking prevents race conditions
- Transaction rollback on errors

### Audit Trail
- Complete transaction history in `ledger_transactions`
- Income records with full context
- Processing timestamps and status

### Access Control
- Job only processes completed orders
- Validates order ownership and status
- No external input processing

## Monitoring and Logging

### Log Levels
- **INFO**: Normal processing, idempotency skips
- **WARNING**: Order not found, missing configs
- **ERROR**: Processing failures, infinite loops

### Key Metrics
- Processing time per order
- Income distribution amounts
- Wallet balance changes
- Error rates and types

### Alerts
- Failed job processing
- Infinite loop detection (>1000 ancestors)
- Missing income configurations

## Future Extensions

### Planned Features
- **AutoPool Distribution**: Separate job for pool processing
- **Club Matrix Processing**: Club progression and payouts
- **Dynamic Configurations**: Runtime config changes
- **Multi-Currency Support**: Enhanced currency handling

### Integration Points
- **Payment Gateways**: Webhook integration
- **Notification System**: User notifications
- **Reporting System**: Income analytics
- **Admin Dashboard**: Real-time monitoring

## Troubleshooting

### Common Issues

#### 1. Job Not Processing
```bash
# Check queue worker status
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed
```

#### 2. Missing Income Records
- Verify income configs exist and are active
- Check user tree structure
- Ensure order is completed

#### 3. Database Errors
- Check foreign key constraints
- Verify enum values
- Ensure nullable fields are properly set

#### 4. Precision Issues
- Verify bcmath extension is installed
- Check decimal column definitions
- Review percentage normalization logic

### Debug Commands
```bash
# Test job with specific order
php artisan tinker
>>> dispatch(new App\Jobs\ProcessPurchaseJob(123));

# Check database state
php artisan tinker
>>> App\Models\IncomeRecord::count();
>>> App\Models\LedgerTransaction::count();
```

## Conclusion

The `ProcessPurchaseJob` provides a complete, production-ready solution for MLM income distribution. It handles all major income types with proper precision, safety, and scalability. The implementation is idempotent, transactional, and includes comprehensive error handling and logging.

The job is designed to be extended with additional income types (AutoPool, Club) while maintaining the same high standards of reliability and performance.

---

**Status**: ✅ **Production Ready**  
**Last Updated**: September 21, 2025  
**Version**: 1.0.0

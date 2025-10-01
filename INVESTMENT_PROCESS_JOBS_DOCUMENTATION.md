# Investment Process Jobs Documentation

## Overview

The investment system now includes automated process jobs to handle daily interest accrual, maturity processing, and investment activation. These jobs ensure that investments are processed correctly and users receive their earnings on time.

## 🚀 **Available Jobs & Commands**

### 1. **ProcessInvestmentJob**
**Purpose**: Process individual investments for daily interest and maturity

**Triggers**:
- Daily interest accrual for active investments
- Maturity processing for completed investments
- Referral commission distribution

**Features**:
- ✅ Idempotent (prevents duplicate processing)
- ✅ Transactional safety
- ✅ Comprehensive logging
- ✅ Error handling

### 2. **ProcessInvestmentsCommand**
**Purpose**: Process all active investments in batch

**Usage**:
```bash
# Process all investments (daily + maturity)
php artisan investments:process

# Process only daily interest
php artisan investments:process --type=daily

# Process only maturity
php artisan investments:process --type=maturity
```

### 3. **ActivateInvestmentsCommand**
**Purpose**: Activate pending investments (process payment)

**Usage**:
```bash
# Activate all pending investments
php artisan investments:activate

# Activate for specific user
php artisan investments:activate --user-id=5

# Activate specific investment
php artisan investments:activate --investment-id=10
```

---

## 📊 **Investment Processing Flow**

### **1. Investment Creation**
```
User creates investment → Status: PENDING
```

### **2. Investment Activation**
```
Admin runs: php artisan investments:activate
↓
- Deducts amount from user's main wallet
- Creates payment ledger transaction
- Processes referral commission
- Changes status to ACTIVE
- Sets start_at and end_at dates
```

### **3. Daily Interest Processing**
```
Daily cron job: php artisan investments:process --type=daily
↓
- Calculates daily interest (amount × daily_profit_percent)
- Credits user's main wallet
- Creates ledger transaction
- Creates income record
- Updates accrued_interest
```

### **4. Maturity Processing**
```
Daily cron job: php artisan investments:process --type=maturity
↓
- Calculates final payout (principal + accrued_interest)
- Credits user's main wallet
- Creates ledger transaction
- Creates income record
- Changes status to COMPLETED
- Sets matured_at timestamp
```

---

## 🔧 **Setup Instructions**

### **1. Register Commands**
Add to `app/Console/Kernel.php`:

```php
protected $commands = [
    Commands\ProcessInvestmentsCommand::class,
    Commands\ActivateInvestmentsCommand::class,
];
```

### **2. Schedule Daily Processing**
Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Process investments daily at 2 AM
    $schedule->command('investments:process --type=daily')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->runInBackground();

    // Process maturity daily at 3 AM
    $schedule->command('investments:process --type=maturity')
        ->dailyAt('03:00')
        ->withoutOverlapping()
        ->runInBackground();
}
```

### **3. Queue Configuration**
Ensure your queue worker is running:

```bash
# Start queue worker
php artisan queue:work --verbose --tries=3 --timeout=90
```

---

## 📈 **Investment Status Flow**

```
PENDING → ACTIVE → COMPLETED
   ↓         ↓         ↓
Payment   Daily    Final
Process   Interest  Payout
```

### **Status Descriptions**

| Status | Description | Processing |
|--------|-------------|------------|
| `PENDING` | Investment created, awaiting payment | Manual activation required |
| `ACTIVE` | Payment processed, earning daily interest | Daily interest accrual |
| `COMPLETED` | Investment matured, final payout made | No further processing |
| `CANCELLED` | Investment cancelled before activation | No processing |
| `WITHDRAWN` | Investment withdrawn early | No processing |

---

## 💰 **Financial Processing**

### **Daily Interest Calculation**
```php
$dailyInterest = $investment->amount * ($investment->daily_profit_percent / 100);
```

### **Final Payout Calculation**
```php
$finalPayout = $investment->amount + $investment->accrued_interest;
```

### **Referral Commission**
```php
$referralCommission = $investment->amount * ($plan->referral_percent / 100);
```

---

## 🔍 **Monitoring & Logs**

### **Log Messages**
- `ProcessInvestmentJob: Processing investment {id}`
- `ProcessInvestmentJob: Credited daily interest {amount} to user {user_id}`
- `ProcessInvestmentJob: Processed maturity for investment {id} with payout {amount}`
- `ProcessInvestmentJob: Credited referral commission {amount} to referrer {referrer_id}`

### **Database Records Created**
- **Ledger Transactions**: All financial movements
- **Income Records**: Income distribution tracking
- **Wallet Updates**: Balance changes

---

## 🚨 **Error Handling**

### **Common Issues**
1. **Insufficient Wallet Balance**: User doesn't have enough funds for activation
2. **Investment Not Found**: Investment ID doesn't exist
3. **Already Processed**: Investment already processed today (idempotency)

### **Retry Logic**
- Jobs are retried up to 3 times on failure
- Failed jobs are logged for manual review
- Idempotency prevents duplicate processing

---

## 📋 **Admin Tasks**

### **Daily Tasks**
1. **Monitor Queue**: Ensure queue worker is running
2. **Check Logs**: Review processing logs for errors
3. **Activate Investments**: Run activation command for new investments

### **Weekly Tasks**
1. **Review Failed Jobs**: Check for processing failures
2. **Monitor Performance**: Review processing times
3. **Update Investment Plans**: Modify rates if needed

### **Monthly Tasks**
1. **Financial Reconciliation**: Verify all transactions
2. **Performance Analysis**: Review investment performance
3. **System Maintenance**: Clean up old logs and data

---

## 🎯 **Best Practices**

### **1. Scheduling**
- Run daily interest processing during low-traffic hours
- Use different times for daily vs maturity processing
- Monitor processing times and adjust if needed

### **2. Monitoring**
- Set up alerts for failed jobs
- Monitor queue length and processing times
- Regular log review for errors

### **3. Testing**
- Test with small amounts first
- Verify calculations manually
- Test error scenarios

---

## 🚀 **Ready to Use!**

The investment process jobs are now ready for production use. They provide:

- ✅ **Automated Daily Processing**: No manual intervention needed
- ✅ **Financial Accuracy**: Precise calculations with bcmath
- ✅ **Audit Trail**: Complete transaction history
- ✅ **Error Handling**: Robust error management
- ✅ **Scalability**: Handles large volumes efficiently

**Next Steps**:
1. Set up the cron jobs
2. Start the queue worker
3. Test with sample investments
4. Monitor the first few days of processing

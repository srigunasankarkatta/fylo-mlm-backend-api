# Investment Transactions API Documentation

## Overview
Comprehensive API for managing and analyzing investment transactions in the admin portal with advanced filtering, statistics, and export capabilities.

## Authentication
- **Required**: JWT Token
- **Header**: `Authorization: Bearer {your_jwt_token}`
- **Role Required**: `admin`

---

## 📋 **API Endpoints**

### 1. **Get Investment Transactions** 
`GET /api/admin/investment-transactions`

**Description**: Retrieve investment transactions with comprehensive filtering options.

#### **Query Parameters**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `plan_id` | integer | Filter by specific investment plan | `?plan_id=1` |
| `user_id` | integer | Filter by specific user | `?user_id=5` |
| `status` | string | Filter by status | `?status=active` |
| `statuses` | string/array | Filter by multiple statuses | `?statuses=pending,active` |
| `amount_from` | decimal | Minimum amount filter | `?amount_from=100` |
| `amount_to` | decimal | Maximum amount filter | `?amount_to=1000` |
| `invested_from` | date | Investment date from | `?invested_from=2025-01-01` |
| `invested_to` | date | Investment date to | `?invested_to=2025-01-31` |
| `start_from` | date | Start date from | `?start_from=2025-01-01` |
| `start_to` | date | Start date to | `?start_to=2025-01-31` |
| `end_from` | date | End date from | `?end_from=2025-01-01` |
| `end_to` | date | End date to | `?end_to=2025-01-31` |
| `duration_days` | integer | Filter by duration | `?duration_days=30` |
| `profit_from` | decimal | Minimum daily profit % | `?profit_from=1.5` |
| `profit_to` | decimal | Maximum daily profit % | `?profit_to=5.0` |
| `has_referral` | boolean | Filter by referral status | `?has_referral=true` |
| `search` | string | Search in user/plan details | `?search=john` |
| `order_by` | string | Sort field | `?order_by=created_at` |
| `order_direction` | string | Sort direction | `?order_direction=desc` |
| `per_page` | integer | Results per page | `?per_page=50` |

#### **Example Request**
```http
GET /api/admin/investment-transactions?status=active&amount_from=100&per_page=20
Authorization: Bearer your_jwt_token
```

#### **Response**
```json
{
    "success": true,
    "message": "Investment transactions retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 5,
                "investment_plan_id": 1,
                "amount": "1000.00000000",
                "daily_profit_percent": "2.500000",
                "duration_days": 30,
                "invested_at": "2025-01-01T10:00:00.000000Z",
                "start_at": "2025-01-01T10:00:00.000000Z",
                "end_at": "2025-01-31T10:00:00.000000Z",
                "matured_at": null,
                "accrued_interest": "25.00000000",
                "total_payout": "0.00000000",
                "status": "active",
                "referrer_id": 3,
                "referral_commission": "50.00000000",
                "created_at": "2025-01-01T10:00:00.000000Z",
                "updated_at": "2025-01-01T10:00:00.000000Z",
                "user": {
                    "id": 5,
                    "name": "John Doe",
                    "email": "john@example.com",
                    "phone": "1234567890",
                    "referral_code": "U1234567890"
                },
                "investmentPlan": {
                    "id": 1,
                    "name": "Premium Plan",
                    "code": "PREMIUM",
                    "daily_profit_percent": "2.50"
                },
                "referrer": {
                    "id": 3,
                    "name": "Jane Smith",
                    "email": "jane@example.com"
                }
            }
        ],
        "total": 150,
        "per_page": 20,
        "last_page": 8
    }
}
```

---

### 2. **Get Investment Transaction Statistics**
`GET /api/admin/investment-transactions/stats`

**Description**: Get comprehensive statistics and analytics for investment transactions.

#### **Response**
```json
{
    "success": true,
    "message": "Investment transaction statistics retrieved successfully",
    "data": {
        "overview": {
            "total_investments": 150,
            "active_investments": 120,
            "pending_investments": 20,
            "completed_investments": 8,
            "cancelled_investments": 2,
            "total_invested_amount": "150000.00000000",
            "total_accrued_interest": "3750.00000000",
            "total_payouts": "12000.00000000",
            "total_referral_commissions": "7500.00000000"
        },
        "plan_breakdown": [
            {
                "name": "Premium Plan",
                "code": "PREMIUM",
                "daily_profit_percent": "2.50",
                "investment_count": 80,
                "total_invested": "80000.00000000",
                "total_interest": "2000.00000000",
                "total_payouts": "5000.00000000"
            }
        ],
        "status_breakdown": [
            {
                "status": "active",
                "count": 120,
                "total_invested": "120000.00000000",
                "total_interest": "3000.00000000",
                "total_payouts": "0.00000000"
            }
        ],
        "daily_stats": [
            {
                "date": "2025-01-01",
                "investments": 5,
                "total_invested": "5000.00000000",
                "total_interest": "125.00000000"
            }
        ],
        "recent_transactions": [...]
    }
}
```

---

### 3. **Get Investment Transaction Dashboard**
`GET /api/admin/investment-transactions/dashboard`

**Description**: Get dashboard data with charts and analytics.

#### **Query Parameters**
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `period` | integer | Number of days to analyze | `30` |

#### **Response**
```json
{
    "success": true,
    "message": "Investment transaction dashboard data retrieved successfully",
    "data": {
        "overview": {
            "total_investments": 150,
            "active_investments": 120,
            "total_invested_amount": "150000.00000000",
            "total_accrued_interest": "3750.00000000",
            "average_investment_value": "1000.00000000"
        },
        "chart_data": [
            {
                "date": "2025-01-01",
                "total": 5,
                "total_invested": "5000.00000000",
                "total_interest": "125.00000000"
            }
        ],
        "top_plans": [
            {
                "name": "Premium Plan",
                "code": "PREMIUM",
                "investment_count": 80,
                "total_invested": "80000.00000000"
            }
        ],
        "recent_activity": [...]
    }
}
```

---

### 4. **Get Investment Transaction Details**
`GET /api/admin/investment-transactions/{id}`

**Description**: Get detailed information about a specific investment transaction.

#### **Response**
```json
{
    "success": true,
    "message": "Investment transaction details retrieved successfully",
    "data": {
        "transaction": {
            "id": 1,
            "user_id": 5,
            "investment_plan_id": 1,
            "amount": "1000.00000000",
            "daily_profit_percent": "2.500000",
            "duration_days": 30,
            "status": "active",
            "user": {...},
            "investmentPlan": {...},
            "referrer": {...}
        },
        "income_records": [
            {
                "id": 1,
                "user_id": 5,
                "income_type": "investment_interest",
                "amount": "25.00000000",
                "reference_id": "investment_1_daily_interest"
            }
        ],
        "ledger_transactions": [
            {
                "id": 1,
                "user_from": null,
                "user_to": 5,
                "type": "investment_payment",
                "amount": "1000.00000000",
                "description": "Investment payment for Premium Plan"
            }
        ],
        "user_wallets": [
            {
                "id": 1,
                "user_id": 5,
                "wallet_type": "main",
                "balance": "500.00000000",
                "currency": "USD"
            }
        ]
    }
}
```

---

### 5. **Update Investment Transaction Status**
`PATCH /api/admin/investment-transactions/{id}/status`

**Description**: Admin override to update investment transaction status.

#### **Request Body**
```json
{
    "status": "active",
    "notes": "Admin approved investment"
}
```

#### **Response**
```json
{
    "success": true,
    "message": "Investment transaction status updated successfully",
    "data": {
        "id": 1,
        "status": "active",
        "metadata": {
            "admin_notes": "Admin approved investment",
            "admin_updated_at": "2025-01-01T12:00:00.000000Z",
            "status_changed_from": "pending"
        },
        "user": {...},
        "investmentPlan": {...}
    }
}
```

---

### 6. **Activate Investment**
`POST /api/admin/investment-transactions/{id}/activate`

**Description**: Activate a pending investment (process payment and start earning).

#### **Response**
```json
{
    "success": true,
    "message": "Investment activated successfully",
    "data": {
        "id": 1,
        "status": "active",
        "start_at": "2025-01-01T12:00:00.000000Z",
        "end_at": "2025-01-31T12:00:00.000000Z",
        "user": {...},
        "investmentPlan": {...}
    }
}
```

---

### 7. **Export Investment Transactions to CSV**
`GET /api/admin/investment-transactions/export/csv`

**Description**: Export filtered investment transactions to CSV file.

#### **Query Parameters**
Same filtering parameters as transactions endpoint.

#### **Response**
- **Content-Type**: `text/csv`
- **File**: `investment_transactions_2025-01-01_12-00-00.csv`

#### **CSV Columns**
- ID, User Name, User Email, User Phone, Plan Name, Plan Code, Amount, Daily Profit %, Duration Days, Status, Invested At, Start At, End At, Matured At, Accrued Interest, Total Payout, Referrer Name, Referral Commission, Created At

---

## 🔍 **Filter Examples**

### **Basic Filters**
```http
# Get active investments
GET /api/admin/investment-transactions?status=active

# Get investments for specific plan
GET /api/admin/investment-transactions?plan_id=1

# Get investments for specific user
GET /api/admin/investment-transactions?user_id=5
```

### **Date Range Filters**
```http
# Get investments from last 30 days
GET /api/admin/investment-transactions?invested_from=2025-01-01&invested_to=2025-01-31

# Get investments that started in specific period
GET /api/admin/investment-transactions?start_from=2025-01-01&start_to=2025-01-31
```

### **Amount Range Filters**
```http
# Get investments between $500-$2000
GET /api/admin/investment-transactions?amount_from=500&amount_to=2000

# Get high-value investments
GET /api/admin/investment-transactions?amount_from=5000
```

### **Status Filters**
```http
# Get pending and active investments
GET /api/admin/investment-transactions?statuses=pending,active

# Get completed investments only
GET /api/admin/investment-transactions?status=completed
```

### **Investment Plan Filters**
```http
# Get investments with specific duration
GET /api/admin/investment-transactions?duration_days=30

# Get investments with specific profit range
GET /api/admin/investment-transactions?profit_from=2.0&profit_to=5.0

# Get investments with referrals
GET /api/admin/investment-transactions?has_referral=true
```

### **Search Filters**
```http
# Search by user name or email
GET /api/admin/investment-transactions?search=john

# Search by plan name
GET /api/admin/investment-transactions?search=premium

# Search by referrer name
GET /api/admin/investment-transactions?search=jane
```

---

## 📊 **Investment Status Flow**

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

## 🎯 **Use Cases**

### **Admin Dashboard**
- Monitor investment volume and performance
- Track daily interest accrual
- Identify pending investments
- View recent activity

### **Financial Reporting**
- Export investment data for accounting
- Generate performance reports
- Track referral commissions
- Analyze user investment patterns

### **Investment Management**
- Review pending investments
- Activate investments
- Update investment statuses
- Process withdrawals

### **Analytics**
- Investment plan popularity analysis
- Revenue trend analysis
- User behavior insights
- Performance metrics

---

## 🚀 **Ready to Use!**

All endpoints are now available and ready for integration with your admin portal. The API provides comprehensive filtering, statistics, and export capabilities for managing investment transactions effectively.

### **Available Commands**
```bash
# Process all investments
php artisan investments:process

# Process daily interest only
php artisan investments:process --type=daily

# Process maturity only
php artisan investments:process --type=maturity

# Activate pending investments
php artisan investments:activate
```

### **Scheduling**
Add to your A Panel cron jobs:
```bash
# Daily at 2 AM - Process daily interest
0 2 * * * cd /path/to/your/project && php artisan investments:process --type=daily

# Daily at 3 AM - Process maturity
0 3 * * * cd /path/to/your/project && php artisan investments:process --type=maturity
```

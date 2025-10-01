# Package Transactions API Documentation

## Overview
Comprehensive API for managing and analyzing package transactions in the admin portal with advanced filtering, statistics, and export capabilities.

## Authentication
- **Required**: JWT Token
- **Header**: `Authorization: Bearer {your_jwt_token}`
- **Role Required**: `admin`

---

## 📋 **API Endpoints**

### 1. **Get Package Transactions** 
`GET /api/admin/package-transactions`

**Description**: Retrieve package transactions with comprehensive filtering options.

#### **Query Parameters**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `package_id` | integer | Filter by specific package | `?package_id=1` |
| `user_id` | integer | Filter by specific user | `?user_id=5` |
| `payment_status` | string | Filter by payment status | `?payment_status=completed` |
| `payment_statuses` | string/array | Filter by multiple statuses | `?payment_statuses=pending,completed` |
| `assigned_level` | integer | Filter by package level | `?assigned_level=1` |
| `amount_from` | decimal | Minimum amount filter | `?amount_from=100` |
| `amount_to` | decimal | Maximum amount filter | `?amount_to=1000` |
| `purchase_from` | date | Purchase date from | `?purchase_from=2025-01-01` |
| `purchase_to` | date | Purchase date to | `?purchase_to=2025-01-31` |
| `created_from` | date | Created date from | `?created_from=2025-01-01` |
| `created_to` | date | Created date to | `?created_to=2025-01-31` |
| `processing` | boolean | Filter by processing status | `?processing=true` |
| `processed` | boolean | Filter by processed status | `?processed=false` |
| `search` | string | Search in user/package details | `?search=john` |
| `order_by` | string | Sort field | `?order_by=created_at` |
| `order_direction` | string | Sort direction | `?order_direction=desc` |
| `per_page` | integer | Results per page | `?per_page=50` |

#### **Example Request**
```http
GET /api/admin/package-transactions?payment_status=completed&amount_from=100&per_page=20
Authorization: Bearer your_jwt_token
```

#### **Response**
```json
{
    "success": true,
    "message": "Package transactions retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 5,
                "package_id": 1,
                "amount_paid": "100.00000000",
                "payment_reference": "PAY_123456",
                "payment_status": "completed",
                "purchase_at": "2025-01-01T10:00:00.000000Z",
                "assigned_level": 1,
                "processing": false,
                "processed_at": "2025-01-01T10:05:00.000000Z",
                "created_at": "2025-01-01T10:00:00.000000Z",
                "updated_at": "2025-01-01T10:05:00.000000Z",
                "user": {
                    "id": 5,
                    "name": "John Doe",
                    "email": "john@example.com",
                    "phone": "1234567890",
                    "referral_code": "U1234567890"
                },
                "package": {
                    "id": 1,
                    "name": "Bronze Package",
                    "code": "BRONZE",
                    "level_number": 1,
                    "price": "100.00"
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

### 2. **Get Transaction Statistics**
`GET /api/admin/package-transactions/stats`

**Description**: Get comprehensive statistics and analytics for package transactions.

#### **Query Parameters**
Same as transactions endpoint for filtering.

#### **Response**
```json
{
    "success": true,
    "message": "Transaction statistics retrieved successfully",
    "data": {
        "overview": {
            "total_transactions": 150,
            "completed_transactions": 120,
            "pending_transactions": 20,
            "failed_transactions": 10,
            "total_revenue": "15000.00000000",
            "average_transaction_value": "125.00000000",
            "processed_transactions": 115,
            "unprocessed_transactions": 35
        },
        "package_breakdown": [
            {
                "name": "Bronze Package",
                "code": "BRONZE",
                "level_number": 1,
                "transaction_count": 80,
                "total_revenue": "8000.00000000"
            }
        ],
        "status_breakdown": [
            {
                "payment_status": "completed",
                "count": 120,
                "revenue": "15000.00000000"
            }
        ],
        "daily_stats": [
            {
                "date": "2025-01-01",
                "transactions": 5,
                "revenue": "500.00000000"
            }
        ],
        "recent_transactions": [...]
    }
}
```

---

### 3. **Get Transaction Dashboard**
`GET /api/admin/package-transactions/dashboard`

**Description**: Get dashboard data with charts and analytics.

#### **Query Parameters**
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `period` | integer | Number of days to analyze | `30` |

#### **Response**
```json
{
    "success": true,
    "message": "Transaction dashboard data retrieved successfully",
    "data": {
        "overview": {
            "total_transactions": 150,
            "completed_transactions": 120,
            "completion_rate": 80.0,
            "total_revenue": "15000.00000000",
            "average_transaction_value": "125.00000000"
        },
        "chart_data": [
            {
                "date": "2025-01-01",
                "total": 5,
                "completed": 4,
                "revenue": "400.00000000"
            }
        ],
        "top_packages": [
            {
                "name": "Bronze Package",
                "code": "BRONZE",
                "transaction_count": 80,
                "revenue": "8000.00000000"
            }
        ],
        "recent_activity": [...]
    }
}
```

---

### 4. **Get Transaction Details**
`GET /api/admin/package-transactions/{id}`

**Description**: Get detailed information about a specific transaction including related income records and ledger transactions.

#### **Response**
```json
{
    "success": true,
    "message": "Transaction details retrieved successfully",
    "data": {
        "transaction": {
            "id": 1,
            "user_id": 5,
            "package_id": 1,
            "amount_paid": "100.00000000",
            "payment_reference": "PAY_123456",
            "payment_status": "completed",
            "purchase_at": "2025-01-01T10:00:00.000000Z",
            "assigned_level": 1,
            "processing": false,
            "processed_at": "2025-01-01T10:05:00.000000Z",
            "user": {...},
            "package": {...}
        },
        "income_records": [
            {
                "id": 1,
                "user_id": 5,
                "income_type": "fasttrack",
                "amount": "10.00000000",
                "reference_id": "1_fasttrack",
                "income_config": {...}
            }
        ],
        "ledger_transactions": [
            {
                "id": 1,
                "user_from": null,
                "user_to": 5,
                "type": "package_purchase",
                "amount": "100.00000000",
                "description": "Package purchase payment"
            }
        ]
    }
}
```

---

### 5. **Update Transaction Status**
`PATCH /api/admin/package-transactions/{id}/status`

**Description**: Admin override to update transaction status.

#### **Request Body**
```json
{
    "payment_status": "completed",
    "payment_reference": "PAY_123456",
    "notes": "Admin approved payment"
}
```

#### **Response**
```json
{
    "success": true,
    "message": "Transaction status updated successfully",
    "data": {
        "id": 1,
        "payment_status": "completed",
        "payment_reference": "PAY_123456",
        "payment_meta": {
            "admin_notes": "Admin approved payment",
            "admin_updated_at": "2025-01-01T12:00:00.000000Z"
        },
        "user": {...},
        "package": {...}
    }
}
```

---

### 6. **Export Transactions to CSV**
`GET /api/admin/package-transactions/export/csv`

**Description**: Export filtered transactions to CSV file.

#### **Query Parameters**
Same filtering parameters as transactions endpoint.

#### **Response**
- **Content-Type**: `text/csv`
- **File**: `package_transactions_2025-01-01_12-00-00.csv`

#### **CSV Columns**
- ID, User Name, User Email, User Phone, Package Name, Package Code, Level, Amount Paid, Payment Status, Payment Reference, Purchase Date, Processed At, Created At

---

## 🔍 **Filter Examples**

### **Basic Filters**
```http
# Get completed transactions
GET /api/admin/package-transactions?payment_status=completed

# Get transactions for specific package
GET /api/admin/package-transactions?package_id=1

# Get transactions for specific user
GET /api/admin/package-transactions?user_id=5
```

### **Date Range Filters**
```http
# Get transactions from last 30 days
GET /api/admin/package-transactions?created_from=2025-01-01&created_to=2025-01-31

# Get transactions purchased in specific period
GET /api/admin/package-transactions?purchase_from=2025-01-01&purchase_to=2025-01-31
```

### **Amount Range Filters**
```http
# Get transactions between $100-$500
GET /api/admin/package-transactions?amount_from=100&amount_to=500

# Get high-value transactions
GET /api/admin/package-transactions?amount_from=1000
```

### **Multiple Status Filters**
```http
# Get pending and completed transactions
GET /api/admin/package-transactions?payment_statuses=pending,completed

# Get failed transactions only
GET /api/admin/package-transactions?payment_status=failed
```

### **Search Filters**
```http
# Search by user name or email
GET /api/admin/package-transactions?search=john

# Search by package name
GET /api/admin/package-transactions?search=bronze

# Search by payment reference
GET /api/admin/package-transactions?search=PAY_123
```

### **Processing Filters**
```http
# Get unprocessed transactions
GET /api/admin/package-transactions?processed=false

# Get transactions currently being processed
GET /api/admin/package-transactions?processing=true
```

### **Combined Filters**
```http
# Complex filter example
GET /api/admin/package-transactions?payment_status=completed&amount_from=100&created_from=2025-01-01&search=bronze&per_page=50&order_by=amount_paid&order_direction=desc
```

---

## 📊 **Use Cases**

### **Admin Dashboard**
- Monitor transaction volume and revenue
- Track completion rates
- Identify failed transactions
- View recent activity

### **Financial Reporting**
- Export transaction data for accounting
- Generate revenue reports
- Track package performance
- Analyze user spending patterns

### **Transaction Management**
- Review pending transactions
- Update transaction statuses
- Investigate failed payments
- Process refunds

### **Analytics**
- Package popularity analysis
- Revenue trend analysis
- User behavior insights
- Performance metrics

---

## 🚀 **Ready to Use!**

All endpoints are now available and ready for integration with your admin portal. The API provides comprehensive filtering, statistics, and export capabilities for managing package transactions effectively.

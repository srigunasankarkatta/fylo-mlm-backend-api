# 💰 Income API Documentation

This document provides comprehensive documentation for all customer-facing income APIs in the MLM system.

## 📋 Overview

The Income API allows users to view their earnings from all 4 income types:
- **Level Income**: Fixed amount per upline level
- **Fasttrack Income**: Percentage from direct referrals
- **Club Income**: Matrix expansion rewards
- **Company Allocation**: Pool distribution

## 🔐 Authentication

All income APIs require JWT authentication. Include the token in the Authorization header:

```http
Authorization: Bearer <your_jwt_token>
```

## 📊 API Endpoints

### 1. Income Summary
**GET** `/api/user/income/summary`

Get a comprehensive overview of user's income and wallet balances.

#### Response
```json
{
  "status": "success",
  "message": "Income summary retrieved successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "income_summary": {
      "total_paid": 150.50,
      "total_pending": 25.00,
      "total_reversed": 0.00,
      "total_earned": 175.50,
      "by_type": {
        "level": 50.00,
        "fasttrack": 75.50,
        "club": 25.00,
        "company_allocation": 0.00
      }
    },
    "wallet_summary": {
      "total_balance": 175.50,
      "total_pending": 25.00,
      "wallets": {
        "commission": {
          "balance": 50.00,
          "pending_balance": 0.00,
          "currency": "USD"
        },
        "fasttrack": {
          "balance": 75.50,
          "pending_balance": 25.00,
          "currency": "USD"
        },
        "club": {
          "balance": 25.00,
          "pending_balance": 0.00,
          "currency": "USD"
        },
        "autopool": {
          "balance": 0.00,
          "pending_balance": 0.00,
          "currency": "USD"
        },
        "main": {
          "balance": 25.00,
          "pending_balance": 0.00,
          "currency": "USD"
        }
      }
    },
    "income_types": {
      "level": "Level Income - Fixed amount per upline level",
      "fasttrack": "Fasttrack Income - Percentage from direct referrals",
      "club": "Club Income - Matrix expansion rewards",
      "company_allocation": "Company Allocation - Pool distribution"
    }
  }
}
```

### 2. Income Records
**GET** `/api/user/income/records`

Get paginated list of user's income records with filtering options.

#### Query Parameters
- `type` (optional): Filter by income type (`level`, `fasttrack`, `club`, `company_allocation`)
- `status` (optional): Filter by status (`pending`, `paid`, `reversed`)
- `currency` (optional): Filter by currency (default: `USD`)
- `from_date` (optional): Filter from date (YYYY-MM-DD)
- `to_date` (optional): Filter to date (YYYY-MM-DD)
- `per_page` (optional): Records per page (default: 20)

#### Example Request
```http
GET /api/user/income/records?type=level&status=paid&per_page=10
Authorization: Bearer <your_jwt_token>
```

#### Response
```json
{
  "status": "success",
  "message": "Income records retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "income_type": "level",
        "amount": "5.00",
        "currency": "USD",
        "status": "paid",
        "description": "Level Income from Jane Smith",
        "origin_user": {
          "id": 2,
          "name": "Jane Smith"
        },
        "package": {
          "id": 1,
          "name": "Bronze Package",
          "level": 1
        },
        "created_at": "2025-09-22T10:30:00.000000Z",
        "updated_at": "2025-09-22T10:30:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/user/income/records?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/user/income/records?page=1",
    "links": [...],
    "next_page_url": null,
    "path": "http://localhost:8000/api/user/income/records",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  }
}
```

### 3. Income by Type
**GET** `/api/user/income/by-type`

Get income totals grouped by type.

#### Response
```json
{
  "status": "success",
  "message": "Income by type retrieved successfully",
  "data": {
    "level": {
      "type": "level",
      "total_paid": 50.00,
      "total_pending": 0.00,
      "total_earned": 50.00,
      "description": "Level Income - Fixed amount earned from each upline level when downline members purchase packages"
    },
    "fasttrack": {
      "type": "fasttrack",
      "total_paid": 75.50,
      "total_pending": 25.00,
      "total_earned": 100.50,
      "description": "Fasttrack Income - Percentage earned from direct referrals when they purchase packages"
    },
    "club": {
      "type": "club",
      "total_paid": 25.00,
      "total_pending": 0.00,
      "total_earned": 25.00,
      "description": "Club Income - Matrix expansion rewards earned when your club matrix fills with new members"
    },
    "company_allocation": {
      "type": "company_allocation",
      "total_paid": 0.00,
      "total_pending": 0.00,
      "total_earned": 0.00,
      "description": "Company Allocation - Pool distribution from company allocation fund"
    }
  }
}
```

### 4. Wallet Details
**GET** `/api/user/wallets`

Get detailed information about user's wallets.

#### Response
```json
{
  "status": "success",
  "message": "Wallet details retrieved successfully",
  "data": [
    {
      "id": 1,
      "wallet_type": "commission",
      "balance": "50.00000000",
      "pending_balance": "0.00000000",
      "currency": "USD",
      "description": "Commission Wallet - Stores Level Income earnings",
      "created_at": "2025-09-22T10:00:00.000000Z",
      "updated_at": "2025-09-22T10:30:00.000000Z"
    },
    {
      "id": 2,
      "wallet_type": "fasttrack",
      "balance": "75.50000000",
      "pending_balance": "25.00000000",
      "currency": "USD",
      "description": "Fasttrack Wallet - Stores Fasttrack Income earnings",
      "created_at": "2025-09-22T10:00:00.000000Z",
      "updated_at": "2025-09-22T10:30:00.000000Z"
    }
  ]
}
```

### 5. Club Matrix
**GET** `/api/user/club/matrix`

Get user's club matrix information showing members in their club structure.

#### Response
```json
{
  "status": "success",
  "message": "Club matrix retrieved successfully",
  "data": {
    "sponsor": {
      "id": 1,
      "name": "John Doe"
    },
    "total_club_income": 25.00,
    "matrix_levels": {
      "1": [
        {
          "id": 1,
          "user": {
            "id": 2,
            "name": "Jane Smith",
            "email": "jane@example.com"
          },
          "status": "active",
          "created_at": "2025-09-22T10:00:00.000000Z"
        }
      ],
      "2": [
        {
          "id": 2,
          "user": {
            "id": 3,
            "name": "Bob Johnson",
            "email": "bob@example.com"
          },
          "status": "active",
          "created_at": "2025-09-22T10:15:00.000000Z"
        }
      ]
    },
    "total_members": 2,
    "levels_filled": 2
  }
}
```

### 6. Ledger Transactions
**GET** `/api/user/ledger/transactions`

Get user's ledger transactions (financial audit trail).

#### Query Parameters
- `type` (optional): Filter by transaction type
- `from_date` (optional): Filter from date (YYYY-MM-DD)
- `to_date` (optional): Filter to date (YYYY-MM-DD)
- `per_page` (optional): Records per page (default: 20)

#### Response
```json
{
  "status": "success",
  "message": "Ledger transactions retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "type": "level_income",
        "amount": "5.00",
        "currency": "USD",
        "description": "Level income for purchase 1 to upline 1",
        "direction": "incoming",
        "from_user": {
          "id": 2,
          "name": "Jane Smith"
        },
        "to_user": {
          "id": 1,
          "name": "John Doe"
        },
        "wallet_type": "commission",
        "created_at": "2025-09-22T10:30:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/user/ledger/transactions?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/user/ledger/transactions?page=1",
    "links": [...],
    "next_page_url": null,
    "path": "http://localhost:8000/api/user/ledger/transactions",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  }
}
```

## 🔍 Income Types Explained

### Level Income
- **Trigger**: When any downline member purchases a package
- **Amount**: Fixed amount per upline level (configurable)
- **Recipients**: All ancestors in the placement tree
- **Wallet**: Commission wallet

### Fasttrack Income
- **Trigger**: When direct referral purchases a package
- **Amount**: Percentage of package price (configurable)
- **Recipients**: Immediate parent
- **Wallet**: Fasttrack wallet

### Club Income
- **Trigger**: When user joins/purchases filling club matrix
- **Amount**: Level-based fixed amounts (configurable)
- **Recipients**: Upline matrix members
- **Wallet**: Club wallet
- **Structure**: 10-level deep matrix with 4 children per level

### Company Allocation
- **Trigger**: On any package purchase
- **Amount**: Percentage of package price (configurable)
- **Recipients**: Company pool
- **Wallet**: Company total wallet

## 📱 Example Usage

### Get Income Summary
```bash
curl -X GET "http://localhost:8000/api/user/income/summary" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

### Get Level Income Records
```bash
curl -X GET "http://localhost:8000/api/user/income/records?type=level&status=paid" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

### Get Wallet Details
```bash
curl -X GET "http://localhost:8000/api/user/wallets" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

## ⚠️ Error Responses

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Token not provided",
  "errors": null,
  "meta": null
}
```

### 403 Forbidden
```json
{
  "status": "error",
  "message": "Access denied",
  "errors": null,
  "meta": null
}
```

### 422 Validation Error
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "type": ["The selected type is invalid."]
  },
  "meta": null
}
```

## 🎯 Best Practices

1. **Always include JWT token** in Authorization header
2. **Use pagination** for large datasets (income records, ledger transactions)
3. **Filter by date ranges** to improve performance
4. **Cache wallet balances** on client side for better UX
5. **Handle errors gracefully** and show user-friendly messages

## 📊 Rate Limiting

All income APIs are subject to rate limiting:
- **100 requests per minute** per user
- **1000 requests per hour** per user

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 99
X-RateLimit-Reset: 1640995200
```

---

## ✅ Summary

The Income API provides comprehensive access to all 4 income types in the MLM system:

- ✅ **Income Summary** - Overview of all earnings
- ✅ **Income Records** - Detailed transaction history
- ✅ **Income by Type** - Breakdown by income type
- ✅ **Wallet Details** - Individual wallet information
- ✅ **Club Matrix** - Club structure visualization
- ✅ **Ledger Transactions** - Financial audit trail

All APIs are **production-ready** with proper authentication, validation, pagination, and error handling.

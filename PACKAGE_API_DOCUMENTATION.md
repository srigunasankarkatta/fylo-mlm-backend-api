# User Package API Documentation

This document provides comprehensive API documentation for the user package system, allowing regular users to browse, purchase, and manage packages.

## Base URL
```
http://localhost:8000/api
```

## Authentication
All protected endpoints require JWT authentication:
```
Authorization: Bearer <jwt_token>
```

---

## 📦 **Public Package Endpoints**

### 1. List Available Packages

**Endpoint**: `GET /api/packages`

**Description**: Retrieve a paginated list of active packages available for purchase.

**Query Parameters**:
- `min_price` (optional): Filter packages with price >= this value
- `max_price` (optional): Filter packages with price <= this value  
- `per_page` (optional): Number of packages per page (default: 20)

**Example Request**:
```bash
GET /api/packages?min_price=50&max_price=500&per_page=10
```

**Success Response (200)**:
```json
{
  "status": "success",
  "message": "Packages retrieved",
  "data": [
    {
      "id": 1,
      "code": "BRONZE",
      "name": "Bronze",
      "price": "100.00000000",
      "level_number": 1,
      "is_active": true,
      "description": "Entry level package for beginners",
      "created_at": "2025-01-21T10:00:00.000000Z",
      "updated_at": "2025-01-21T10:00:00.000000Z"
    }
  ],
  "meta": {
    "pagination": {
      "total": 3,
      "count": 3,
      "per_page": 20,
      "current_page": 1,
      "total_pages": 1
    }
  }
}
```

### 2. View Package Details

**Endpoint**: `GET /api/packages/{id}`

**Description**: Retrieve detailed information about a specific package.

**Path Parameters**:
- `id` (required): Package ID

**Example Request**:
```bash
GET /api/packages/1
```

**Success Response (200)**:
```json
{
  "status": "success",
  "message": "Package retrieved",
  "data": {
    "id": 1,
    "code": "BRONZE",
    "name": "Bronze",
    "price": "100.00000000",
    "level_number": 1,
    "is_active": true,
    "description": "Entry level package for beginners",
    "created_at": "2025-01-21T10:00:00.000000Z",
    "updated_at": "2025-01-21T10:00:00.000000Z"
  }
}
```

**Error Response (404)**:
```json
{
  "status": "error",
  "message": "Package not found"
}
```

---

## 🛒 **User Package Management (Protected)**

### 3. Initiate Package Purchase

**Endpoint**: `POST /api/user/packages`

**Description**: Create a new package purchase order. This endpoint is idempotent - calling it multiple times with the same `idempotency_key` will return the same order.

**Headers**:
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

**Request Body**:
```json
{
  "package_id": 1,
  "amount": 100.00,
  "payment_gateway": "razorpay",
  "idempotency_key": "unique-client-key-123",
  "meta": {
    "device": "android",
    "user_agent": "Mobile App 1.0"
  }
}
```

**Validation Rules**:
- `package_id`: Required, integer, must exist in packages table
- `amount`: Required, numeric, min: 0
- `payment_gateway`: Required, string, max: 50 characters
- `idempotency_key`: Required, string, max: 100 characters (must be unique per user)
- `meta`: Optional, array (additional metadata)

**Success Response (201)**:
```json
{
  "status": "success",
  "message": "Purchase initiated",
  "data": {
    "id": 1,
    "user_id": 5,
    "package_id": 1,
    "amount_paid": "100.00000000",
    "payment_reference": null,
    "payment_status": "pending",
    "purchase_at": null,
    "assigned_level": 1,
    "payment_meta": {
      "device": "android",
      "user_agent": "Mobile App 1.0"
    },
    "idempotency_key": "unique-client-key-123",
    "processing": false,
    "processed_at": null,
    "created_at": "2025-01-21T17:18:10.000000Z",
    "updated_at": "2025-01-21T17:18:10.000000Z"
  }
}
```

**Error Responses**:
- **422**: Validation error or amount mismatch
- **500**: Server error during order creation

### 4. Confirm Package Purchase

**Endpoint**: `POST /api/user/packages/confirm`

**Description**: Confirm a package purchase after payment processing. This endpoint is idempotent and can be called multiple times safely.

**Headers**:
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

**Request Body**:
```json
{
  "idempotency_key": "unique-client-key-123",
  "payment_reference": "razorpay_txn_abc123",
  "payment_status": "completed",
  "gateway": "razorpay",
  "gateway_meta": {
    "raw": "gateway_response_data",
    "transaction_id": "txn_abc123",
    "fees": "2.50"
  }
}
```

**Validation Rules**:
- `idempotency_key`: Required, string, max: 100 characters
- `payment_reference`: Required, string, max: 255 characters
- `payment_status`: Required, enum: ['completed', 'failed']
- `gateway`: Required, string
- `gateway_meta`: Optional, array (gateway-specific data)

**Success Response (200)**:
```json
{
  "status": "success",
  "message": "Purchase confirmed",
  "data": null
}
```

**Error Responses**:
- **400**: Order not found or payment failed
- **500**: Server error during confirmation

### 5. List User Packages

**Endpoint**: `GET /api/user/packages`

**Description**: Retrieve a paginated list of packages purchased by the authenticated user.

**Headers**:
```
Authorization: Bearer <jwt_token>
```

**Query Parameters**:
- `per_page` (optional): Number of packages per page (default: 20)

**Example Request**:
```bash
GET /api/user/packages?per_page=10
```

**Success Response (200)**:
```json
{
  "status": "success",
  "message": "User packages retrieved",
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "package_id": 1,
      "amount_paid": "100.00000000",
      "payment_reference": "razorpay_txn_abc123",
      "payment_status": "completed",
      "purchase_at": "2025-01-21T17:18:59.000000Z",
      "assigned_level": 1,
      "payment_meta": {
        "device": "android",
        "raw": "gateway_response_data"
      },
      "idempotency_key": "unique-client-key-123",
      "processing": false,
      "processed_at": "2025-01-21T17:19:00.000000Z",
      "created_at": "2025-01-21T17:18:10.000000Z",
      "updated_at": "2025-01-21T17:19:00.000000Z",
      "package": {
        "id": 1,
        "code": "BRONZE",
        "name": "Bronze",
        "price": "100.00000000",
        "level_number": 1,
        "is_active": true,
        "description": "Entry level package for beginners"
      }
    }
  ],
  "meta": {
    "pagination": {
      "total": 1,
      "count": 1,
      "per_page": 20,
      "current_page": 1,
      "total_pages": 1
    }
  }
}
```

### 6. View User Package Details

**Endpoint**: `GET /api/user/packages/{id}`

**Description**: Retrieve detailed information about a specific user package purchase.

**Headers**:
```
Authorization: Bearer <jwt_token>
```

**Path Parameters**:
- `id` (required): User package ID

**Example Request**:
```bash
GET /api/user/packages/1
```

**Success Response (200)**:
```json
{
  "status": "success",
  "message": "User package retrieved",
  "data": {
    "id": 1,
    "user_id": 5,
    "package_id": 1,
    "amount_paid": "100.00000000",
    "payment_reference": "razorpay_txn_abc123",
    "payment_status": "completed",
    "purchase_at": "2025-01-21T17:18:59.000000Z",
    "assigned_level": 1,
    "payment_meta": {
      "device": "android",
      "raw": "gateway_response_data"
    },
    "idempotency_key": "unique-client-key-123",
    "processing": false,
    "processed_at": "2025-01-21T17:19:00.000000Z",
    "created_at": "2025-01-21T17:18:10.000000Z",
    "updated_at": "2025-01-21T17:19:00.000000Z",
    "package": {
      "id": 1,
      "code": "BRONZE",
      "name": "Bronze",
      "price": "100.00000000",
      "level_number": 1,
      "is_active": true,
      "description": "Entry level package for beginners"
    }
  }
}
```

**Error Response (404)**:
```json
{
  "status": "error",
  "message": "User package not found"
}
```

---

## 🔄 **Complete Purchase Flow Example**

### Step 1: Browse Packages
```bash
GET /api/packages
```

### Step 2: Initiate Purchase
```bash
POST /api/user/packages
Authorization: Bearer <token>
Content-Type: application/json

{
  "package_id": 1,
  "amount": 100.00,
  "payment_gateway": "razorpay",
  "idempotency_key": "purchase-2025-01-21-001",
  "meta": {"device": "mobile"}
}
```

### Step 3: Process Payment
*[External payment gateway integration - not part of this API]*

### Step 4: Confirm Purchase
```bash
POST /api/user/packages/confirm
Authorization: Bearer <token>
Content-Type: application/json

{
  "idempotency_key": "purchase-2025-01-21-001",
  "payment_reference": "gateway_txn_123",
  "payment_status": "completed",
  "gateway": "razorpay",
  "gateway_meta": {"fees": "2.50"}
}
```

### Step 5: Verify Purchase
```bash
GET /api/user/packages
Authorization: Bearer <token>
```

---

## 🛡️ **Security Features**

### Idempotency
- All purchase operations are idempotent using `idempotency_key`
- Duplicate requests with the same key return the existing order
- Prevents accidental double-charging

### Authentication
- JWT-based authentication for all protected endpoints
- Role-based access control (only users, not admins)
- Token validation on every request

### Validation
- Amount validation against package price
- Package existence and active status checks
- Input sanitization and validation

### Transaction Safety
- Database transactions for data consistency
- Atomic operations for purchase processing
- Proper error handling and rollback

---

## 📊 **Payment Status Flow**

```
pending → completed ✅
pending → failed ❌
```

**Status Descriptions**:
- `pending`: Order created, awaiting payment confirmation
- `completed`: Payment successful, order processed
- `failed`: Payment failed or was rejected

---

## 🔧 **Backend Processing**

### Purchase Confirmation Triggers:
1. **ProcessPurchaseJob** dispatched for income distribution
2. **Level Income** calculation and distribution
3. **Fasttrack** income processing
4. **AutoPool** entry creation
5. **Club Matrix** progression checks

### Database Updates:
- `user_packages` table updated with payment details
- `income_records` created for distributions
- `ledger_transactions` recorded for wallet updates
- `auto_pool_entries` created for pool participation

---

## 🧪 **Testing Examples**

### cURL Examples

**List Packages**:
```bash
curl -X GET "http://localhost:8000/api/packages"
```

**Initiate Purchase**:
```bash
curl -X POST "http://localhost:8000/api/user/packages" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "package_id": 1,
    "amount": 100.00,
    "payment_gateway": "razorpay",
    "idempotency_key": "test-123",
    "meta": {"device": "test"}
  }'
```

**Confirm Purchase**:
```bash
curl -X POST "http://localhost:8000/api/user/packages/confirm" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "test-123",
    "payment_reference": "txn_123",
    "payment_status": "completed",
    "gateway": "razorpay"
  }'
```

---

## 📝 **Error Handling**

All endpoints return consistent error responses:

```json
{
  "status": "error",
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

**Common HTTP Status Codes**:
- `200`: Success
- `201`: Created (purchase initiated)
- `400`: Bad Request (validation errors)
- `401`: Unauthorized (invalid/missing token)
- `404`: Not Found (package/user package not found)
- `422`: Validation Error
- `500`: Server Error

---

## 🚀 **Ready for Production**

The User Package API system is fully functional and ready for frontend integration. It provides:

- ✅ **Complete purchase flow** from browsing to confirmation
- ✅ **Idempotent operations** preventing duplicate charges
- ✅ **JWT authentication** with role-based access
- ✅ **Comprehensive validation** and error handling
- ✅ **Database transactions** ensuring data consistency
- ✅ **Background processing** for income distribution
- ✅ **RESTful design** following API best practices

The system is production-ready and can handle real payment gateway integrations with minimal modifications.

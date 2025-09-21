# User Authentication Implementation

This document outlines the complete user authentication system implementation for the Fylo MLM Backend API, separated from admin APIs in dedicated folders.

## 📁 Folder Structure

```
app/Http/Controllers/Auth/
├── UserAuthController.php          # Main user authentication controller

app/Http/Middleware/
├── EnsureUserRole.php              # Middleware to ensure user role (not admin)
└── JwtRevocationCheck.php          # Middleware to check JWT revocation status
```

## 🎯 Goals & Rules

- **Registration**: Users can register with optional `referral_code`
- **Authentication**: JWT-based authentication with access tokens
- **Logout**: Revokes current JWT token (stores `jti` in `jwt_revocations` table)
- **Refresh**: Issues new JWT token
- **Profile**: Returns authenticated user profile
- **Role Protection**: Only users with `user` role (not `admin`) can access user routes
- **API Responses**: All responses use `ApiResponse` trait

## 🛣️ Routes Added

**Location**: `routes/api.php`

```php
// Public routes (no authentication required)
Route::post('register', [UserAuthController::class, 'register']);
Route::post('login', [UserAuthController::class, 'login']);

// Protected routes (JWT authentication required)
Route::middleware(['auth:api', 'role:user'])->group(function () {
    Route::post('logout', [UserAuthController::class, 'logout']);
    Route::get('me', [UserAuthController::class, 'me']);
    Route::post('refresh', [UserAuthController::class, 'refresh']);
});
```

## 🎮 UserAuthController Methods

### 1. `register(Request $request)`
- **Purpose**: Register new user
- **Validation**: name, email (optional), phone (optional), password, referral_code (optional)
- **Features**:
  - Auto-generates unique referral code
  - Links to sponsor if referral_code provided
  - Auto-assigns `user` role
  - Auto-login after registration
  - Returns JWT token

### 2. `login(Request $request)`
- **Purpose**: Authenticate user and return JWT token
- **Validation**: email OR phone, password
- **Features**:
  - Accepts email or phone for login
  - Prevents admin users from using user login
  - Records session in `jwt_revocations` table
  - Returns JWT token with user data

### 3. `logout(Request $request)`
- **Purpose**: Revoke current JWT token
- **Features**:
  - Marks token as revoked in `jwt_revocations` table
  - Invalidates JWT token
  - Removes session entry

### 4. `me(Request $request)`
- **Purpose**: Return authenticated user profile
- **Returns**: Current user data

### 5. `refresh(Request $request)`
- **Purpose**: Issue new JWT token
- **Features**:
  - Generates new JWT token
  - Updates session record with new `jti`
  - Returns new token with expiration

## 🛡️ Middleware

### EnsureUserRole
- **Purpose**: Ensures user has `user` role (not `admin`)
- **Usage**: `'ensure.user'` middleware alias
- **Features**:
  - Checks authentication
  - Prevents admin access to user routes

### JwtRevocationCheck
- **Purpose**: Validates JWT token against revocation list
- **Usage**: `'jwt.revocation'` middleware alias
- **Features**:
  - Checks if token `jti` is revoked
  - Returns 401 if token is revoked

## 🗄️ Database

### JWT Revocations Table
**Migration**: `2025_09_21_154017_create_jwt_revocations_table.php`

```sql
CREATE TABLE jwt_revocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    jti VARCHAR(100) UNIQUE NOT NULL,
    device_fingerprint VARCHAR(100) NULL,
    revoked_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_revoked (user_id, revoked_at),
    INDEX idx_expires (expires_at)
);
```

### Roles Seeder
**File**: `database/seeders/RolesSeeder.php`

```php
Role::firstOrCreate(['name' => 'admin']);
Role::firstOrCreate(['name' => 'user']);
```

## ⚙️ Configuration

### Middleware Registration
**File**: `bootstrap/app.php`

```php
$middleware->alias([
    'ensure.user' => \App\Http\Middleware\EnsureUserRole::class,
    'jwt.revocation' => \App\Http\Middleware\JwtRevocationCheck::class,
]);
```

## 🔧 Token Revocation System

### How It Works
1. **Login**: JWT `jti` is stored in `jwt_revocations` with `revoked_at = NULL`
2. **Logout**: Token `jti` is marked as revoked (`revoked_at = now()`)
3. **Validation**: Middleware checks if token `jti` exists and is not revoked
4. **Refresh**: Old `jti` is replaced with new `jti` in the database

### Benefits
- **Security**: Revoked tokens cannot be reused
- **Session Management**: Track active sessions per user
- **Multi-device Support**: Manage multiple device logins
- **Audit Trail**: Track login/logout activities

## 📋 Integration Checklist

- [x] ✅ `tymon/jwt-auth` installed and configured
- [x] ✅ `config/auth.php` uses `api` guard with JWT driver
- [x] ✅ `User` model implements `JWTSubject` and `HasRoles`
- [x] ✅ `jwt_revocations` table created
- [x] ✅ User authentication routes added
- [x] ✅ `UserAuthController` implemented
- [x] ✅ Middleware created and registered
- [x] ✅ Roles seeder created and run
- [x] ✅ All linting errors resolved

## 🧪 Example API Requests

### Registration
```bash
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Secret123",
  "password_confirmation": "Secret123",
  "referral_code": "ALICE123"
}
```

### Login
```bash
POST /api/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "Secret123"
}
```

### Get Profile
```bash
GET /api/me
Authorization: Bearer <jwt_token>
```

### Logout
```bash
POST /api/logout
Authorization: Bearer <jwt_token>
```

### Refresh Token
```bash
POST /api/refresh
Authorization: Bearer <jwt_token>
```

## 🔒 Security Features

1. **Role-based Access**: Users cannot access admin routes
2. **Token Revocation**: Revoked tokens are invalidated
3. **Session Tracking**: Device fingerprinting support
4. **Password Hashing**: Secure password storage
5. **Input Validation**: Comprehensive request validation
6. **Error Handling**: Graceful error responses

## 🚀 Next Steps

1. **Test Endpoints**: Use Postman or similar tool to test all endpoints
2. **Frontend Integration**: Connect with frontend application
3. **Rate Limiting**: Add rate limiting to authentication endpoints
4. **Email Verification**: Add email verification for registration
5. **Password Reset**: Implement password reset functionality
6. **Two-Factor Authentication**: Add 2FA support if needed

## 📝 Notes

- All user authentication is completely separated from admin authentication
- JWT tokens are properly revoked on logout
- Session tracking is optional but recommended for security
- The system supports both email and phone-based login
- Referral system is integrated with user registration
- All responses follow the `ApiResponse` trait format

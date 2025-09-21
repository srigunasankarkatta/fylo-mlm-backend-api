# Authentication API Quick Reference

## 🚀 Quick Start

**Base URL**: `http://localhost:8000/api`

## 📋 Endpoints Summary

| Method | Endpoint | Auth Required | Description |
|--------|----------|---------------|-------------|
| POST | `/register` | ❌ | Register new user |
| POST | `/login` | ❌ | User login |
| GET | `/me` | ✅ | Get user profile |
| POST | `/refresh` | ✅ | Refresh JWT token |
| POST | `/logout` | ✅ | User logout |

## 🔑 Authentication Headers

```javascript
headers: {
  'Authorization': 'Bearer <jwt_token>',
  'Content-Type': 'application/json'
}
```

## 📝 Request Examples

### 1. Register
```javascript
POST /api/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Secret123",
  "password_confirmation": "Secret123",
  "referral_code": "ALICE123" // optional
}
```

### 2. Login
```javascript
POST /api/login
{
  "email": "john@example.com", // OR "phone": "+1234567890"
  "password": "Secret123"
}
```

### 3. Get Profile
```javascript
GET /api/me
Authorization: Bearer <token>
```

### 4. Refresh Token
```javascript
POST /api/refresh
Authorization: Bearer <token>
```

### 5. Logout
```javascript
POST /api/logout
Authorization: Bearer <token>
```

## ✅ Success Response Format
```json
{
  "status": "success",
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

## ❌ Error Response Format
```json
{
  "status": "error",
  "message": "Error description",
  "errors": { /* validation errors */ }
}
```

## 🔧 Frontend Integration

### Store Token
```javascript
localStorage.setItem('access_token', token);
localStorage.setItem('user', JSON.stringify(userData));
```

### Add to Requests
```javascript
headers: {
  'Authorization': `Bearer ${localStorage.getItem('access_token')}`
}
```

### Handle 401 (Token Expired)
```javascript
// Auto refresh token or redirect to login
if (response.status === 401) {
  // Try refresh token or redirect to login
}
```

## 🧪 Test with cURL

```bash
# Register
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@test.com","password":"123456","password_confirmation":"123456"}'

# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@test.com","password":"123456"}'

# Get Profile
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer YOUR_TOKEN"

# Logout
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 📱 Mobile App Headers

```javascript
// React Native / Flutter
headers: {
  'Authorization': 'Bearer ' + token,
  'Content-Type': 'application/json',
  'X-Device-Fingerprint': deviceId // optional for session tracking
}
```

## 🔒 Security Checklist

- [ ] Use HTTPS in production
- [ ] Store tokens securely
- [ ] Implement token refresh
- [ ] Clear tokens on logout
- [ ] Validate inputs before sending
- [ ] Handle network errors gracefully

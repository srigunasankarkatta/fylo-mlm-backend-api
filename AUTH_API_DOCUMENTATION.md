# User Authentication API Documentation

This document provides detailed request and response specifications for frontend integration with the user authentication system.

## Base URL
```
http://localhost:8000/api
```

## Content Type
All requests should include:
```
Content-Type: application/json
```

---

## 1. User Registration

### Endpoint
```
POST /api/register
```

### Request Body
```json
{
  "name": "string (required, max:150)",
  "email": "string (optional, email format, unique)",
  "phone": "string (optional, max:30, unique)",
  "password": "string (required, min:6)",
  "password_confirmation": "string (required, must match password)",
  "referral_code": "string (optional, max:20)"
}
```

### Example Request
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "Secret123",
  "password_confirmation": "Secret123",
  "referral_code": "ALICE123"
}
```

### Success Response (201)
```json
{
  "status": "success",
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "referral_code": "U1234567890",
      "referred_by": 2,
      "status": "active",
      "created_at": "2025-01-21T15:30:00.000000Z",
      "updated_at": "2025-01-21T15:30:00.000000Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

### Error Response (422) - Validation Error
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

### Error Response (500) - Server Error
```json
{
  "status": "error",
  "message": "Registration failed: Database connection error"
}
```

---

## 2. User Login

### Endpoint
```
POST /api/login
```

### Request Body
```json
{
  "email": "string (optional, email format)",
  "phone": "string (optional)",
  "password": "string (required, min:6)"
}
```

**Note**: Either `email` OR `phone` is required, not both.

### Example Request (Email Login)
```json
{
  "email": "john@example.com",
  "password": "Secret123"
}
```

### Example Request (Phone Login)
```json
{
  "phone": "+1234567890",
  "password": "Secret123"
}
```

### Success Response (200)
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "referral_code": "U1234567890",
      "referred_by": 2,
      "status": "active",
      "created_at": "2025-01-21T15:30:00.000000Z",
      "updated_at": "2025-01-21T15:30:00.000000Z"
    }
  }
}
```

### Error Response (401) - Invalid Credentials
```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

### Error Response (403) - Admin Login Attempt
```json
{
  "status": "error",
  "message": "Unauthorized: admin should use admin login"
}
```

### Error Response (422) - Validation Error
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "login": ["Either email or phone is required"]
  }
}
```

---

## 3. Get User Profile

### Endpoint
```
GET /api/me
```

### Headers
```
Authorization: Bearer <jwt_token>
```

### Success Response (200)
```json
{
  "status": "success",
  "message": "User profile",
  "data": {
    "id": 1,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "referral_code": "U1234567890",
    "referred_by": 2,
    "status": "active",
    "created_at": "2025-01-21T15:30:00.000000Z",
    "updated_at": "2025-01-21T15:30:00.000000Z"
  }
}
```

### Error Response (401) - Unauthenticated
```json
{
  "status": "error",
  "message": "Unauthenticated"
}
```

### Error Response (401) - Token Revoked
```json
{
  "status": "error",
  "message": "Token revoked"
}
```

---

## 4. Refresh Token

### Endpoint
```
POST /api/refresh
```

### Headers
```
Authorization: Bearer <jwt_token>
```

### Success Response (200)
```json
{
  "status": "success",
  "message": "Token refreshed",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

### Error Response (401) - Token Refresh Failed
```json
{
  "status": "error",
  "message": "Token refresh failed"
}
```

---

## 5. User Logout

### Endpoint
```
POST /api/logout
```

### Headers
```
Authorization: Bearer <jwt_token>
```

### Success Response (200)
```json
{
  "status": "success",
  "message": "Logged out successfully",
  "data": null
}
```

### Error Response (500) - Logout Failed
```json
{
  "status": "error",
  "message": "Logout failed: Token validation error"
}
```

---

## Frontend Integration Examples

### JavaScript/TypeScript Examples

#### 1. Registration Function
```javascript
async function registerUser(userData) {
  try {
    const response = await fetch('http://localhost:8000/api/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(userData)
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      // Store token in localStorage
      localStorage.setItem('access_token', data.data.access_token);
      localStorage.setItem('user', JSON.stringify(data.data.user));
      return data;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Registration failed:', error);
    throw error;
  }
}

// Usage
const userData = {
  name: 'John Doe',
  email: 'john@example.com',
  password: 'Secret123',
  password_confirmation: 'Secret123',
  referral_code: 'ALICE123'
};

registerUser(userData)
  .then(data => console.log('Registration successful:', data))
  .catch(error => console.error('Registration failed:', error));
```

#### 2. Login Function
```javascript
async function loginUser(credentials) {
  try {
    const response = await fetch('http://localhost:8000/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(credentials)
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      // Store token and user data
      localStorage.setItem('access_token', data.data.access_token);
      localStorage.setItem('user', JSON.stringify(data.data.user));
      return data;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Login failed:', error);
    throw error;
  }
}

// Usage
const credentials = {
  email: 'john@example.com',
  password: 'Secret123'
};

loginUser(credentials)
  .then(data => console.log('Login successful:', data))
  .catch(error => console.error('Login failed:', error));
```

#### 3. Get User Profile
```javascript
async function getUserProfile() {
  try {
    const token = localStorage.getItem('access_token');
    
    const response = await fetch('http://localhost:8000/api/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      }
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      return data.data;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Failed to get profile:', error);
    throw error;
  }
}
```

#### 4. Logout Function
```javascript
async function logoutUser() {
  try {
    const token = localStorage.getItem('access_token');
    
    const response = await fetch('http://localhost:8000/api/logout', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      }
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      // Clear stored data
      localStorage.removeItem('access_token');
      localStorage.removeItem('user');
      return data;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Logout failed:', error);
    throw error;
  }
}
```

#### 5. Token Refresh Function
```javascript
async function refreshToken() {
  try {
    const token = localStorage.getItem('access_token');
    
    const response = await fetch('http://localhost:8000/api/refresh', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      }
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      // Update stored token
      localStorage.setItem('access_token', data.data.access_token);
      return data;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Token refresh failed:', error);
    // Redirect to login if refresh fails
    localStorage.removeItem('access_token');
    localStorage.removeItem('user');
    window.location.href = '/login';
    throw error;
  }
}
```

#### 6. Axios Interceptor for Auto Token Refresh
```javascript
import axios from 'axios';

// Create axios instance
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
  }
});

// Request interceptor to add token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for token refresh
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        const token = localStorage.getItem('access_token');
        const response = await axios.post('http://localhost:8000/api/refresh', {}, {
          headers: { Authorization: `Bearer ${token}` }
        });
        
        const newToken = response.data.data.access_token;
        localStorage.setItem('access_token', newToken);
        
        // Retry original request with new token
        originalRequest.headers.Authorization = `Bearer ${newToken}`;
        return api(originalRequest);
      } catch (refreshError) {
        // Refresh failed, redirect to login
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
        return Promise.reject(refreshError);
      }
    }
    
    return Promise.reject(error);
  }
);

export default api;
```

### React Hook Example
```javascript
import { useState, useEffect } from 'react';

export const useAuth = () => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    const userData = localStorage.getItem('user');
    
    if (token && userData) {
      setUser(JSON.parse(userData));
    }
    setLoading(false);
  }, []);

  const login = async (credentials) => {
    try {
      const data = await loginUser(credentials);
      setUser(data.data.user);
      return data;
    } catch (error) {
      throw error;
    }
  };

  const logout = async () => {
    try {
      await logoutUser();
      setUser(null);
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  const register = async (userData) => {
    try {
      const data = await registerUser(userData);
      setUser(data.data.user);
      return data;
    } catch (error) {
      throw error;
    }
  };

  return {
    user,
    loading,
    login,
    logout,
    register,
    isAuthenticated: !!user
  };
};
```

## Error Handling

### Common HTTP Status Codes
- **200**: Success
- **201**: Created (Registration)
- **401**: Unauthorized (Invalid credentials, expired token)
- **403**: Forbidden (Admin trying to access user routes)
- **422**: Validation Error
- **500**: Server Error

### Error Response Format
All error responses follow this format:
```json
{
  "status": "error",
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Security Notes

1. **Token Storage**: Store JWT tokens securely (consider httpOnly cookies for production)
2. **HTTPS**: Always use HTTPS in production
3. **Token Expiry**: Implement automatic token refresh
4. **Logout**: Always clear tokens on logout
5. **Validation**: Validate all inputs on frontend before sending

## Testing with cURL

### Registration
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Secret123",
    "password_confirmation": "Secret123"
  }'
```

### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "Secret123"
  }'
```

### Get Profile
```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Logout
```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

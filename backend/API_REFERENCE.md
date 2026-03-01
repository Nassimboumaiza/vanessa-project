# API Reference

Base URL: `/api/v1`

## Table of Contents

- [Authentication](#authentication)
- [Products](#products)
- [Categories](#categories)
- [Cart](#cart)
- [Orders](#orders)
- [Wishlist](#wishlist)
- [Coupons](#coupons)
- [Reviews](#reviews)
- [User Profile](#user-profile)
- [Admin Endpoints](#admin-endpoints)

---

## Authentication

### Register
```
POST /api/v1/auth/register
```

**Request Body:**
```json
{
  "first_name": "string (required, max:255)",
  "last_name": "string (required, max:255)",
  "email": "string (required, email, unique)",
  "password": "string (required, min:8, confirmed)",
  "password_confirmation": "string (required)"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": { "id", "name", "email", ... },
    "token": "sanctum_token"
  }
}
```

### Login
```
POST /api/v1/auth/login
```

**Request Body:**
```json
{
  "email": "string (required)",
  "password": "string (required)"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "sanctum_token"
  }
}
```

### Forgot Password
```
POST /api/v1/auth/forgot-password
```

**Request Body:**
```json
{
  "email": "string (required, email)"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password reset link has been sent to your email"
}
```

### Reset Password
```
POST /api/v1/auth/reset-password
```

**Request Body:**
```json
{
  "token": "string (required)",
  "email": "string (required)",
  "password": "string (required, min:8)",
  "password_confirmation": "string (required)"
}
```

### Logout
```
POST /api/v1/auth/logout
```
**Requires:** Authentication

---

## Products

### List Products
```
GET /api/v1/products
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number |
| `per_page` | int | Items per page (default: 15) |
| `category` | int | Filter by category ID |
| `min_price` | float | Minimum price |
| `max_price` | float | Maximum price |
| `sort` | string | Sort field (price, created_at, name) |
| `direction` | string | Sort direction (asc, desc) |

**Response (200):**
```json
{
  "success": true,
  "data": [...],
  "meta": { "current_page", "last_page", "total" }
}
```

### Get Featured Products
```
GET /api/v1/products/featured
```

### Get New Arrivals
```
GET /api/v1/products/new-arrivals
```

### Search Products
```
GET /api/v1/products/search?q={query}
```

### Get Single Product
```
GET /api/v1/products/{slug}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Product Name",
    "slug": "product-name",
    "description": "...",
    "price": 99.99,
    "sale_price": 79.99,
    "stock_quantity": 100,
    "images": [...],
    "variants": [...],
    "category": { ... }
  }
}
```

---

## Categories

### List Categories
```
GET /api/v1/categories
```

### Get Products by Category
```
GET /api/v1/categories/{slug}/products
```

---

## Cart

All cart endpoints require authentication.

### Get Cart
```
GET /api/v1/cart
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "product": { ... },
        "quantity": 2,
        "unit_price": 50.00,
        "total_price": 100.00
      }
    ],
    "subtotal": 100.00,
    "discount_amount": 0,
    "shipping_cost": 15.00,
    "tax_amount": 10.00,
    "total": 125.00
  }
}
```

### Add Item to Cart
```
POST /api/v1/cart/items
```

**Request Body:**
```json
{
  "product_id": "int (required)",
  "quantity": "int (required, min:1)",
  "variant_id": "int (optional)"
}
```

### Batch Sync Cart Items
```
POST /api/v1/cart/sync
```

**Request Body:**
```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

### Update Cart Item
```
PUT /api/v1/cart/items/{id}
```

**Request Body:**
```json
{
  "quantity": "int (required, min:1)"
}
```

### Remove Cart Item
```
DELETE /api/v1/cart/items/{id}
```

### Clear Cart
```
DELETE /api/v1/cart
```

---

## Orders

All order endpoints require authentication.

### List Orders
```
GET /api/v1/orders
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status (pending, processing, shipped, delivered, cancelled) |
| `page` | int | Page number |

### Create Order
```
POST /api/v1/orders
```

**Request Body:**
```json
{
  "payment_method": "string (required: cod, credit_card)",
  "idempotency_key": "string (required, unique)",
  "shipping_address": {
    "first_name": "string (required)",
    "last_name": "string (required)",
    "address_line_1": "string (required)",
    "address_line_2": "string (optional)",
    "city": "string (required)",
    "state": "string (required)",
    "postal_code": "string (required)",
    "country": "string (required)",
    "phone": "string (optional)"
  },
  "billing_address": {
    "first_name": "string (optional)",
    "last_name": "string (optional)",
    "address_line_1": "string (optional)",
    "city": "string (optional)",
    "state": "string (optional)",
    "postal_code": "string (optional)",
    "country": "string (optional)"
  },
  "customer_notes": "string (optional)",
  "coupon_code": "string (optional)"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "order_number": "ORD-20260228-ABC123",
    "status": "pending",
    "payment_status": "pending",
    "subtotal": 100.00,
    "shipping_amount": 15.00,
    "tax_amount": 10.00,
    "total_amount": 125.00,
    "items": [...]
  }
}
```

### Get Order Details
```
GET /api/v1/orders/{id}
```

### Get Order Tracking
```
GET /api/v1/orders/{id}/tracking
```

---

## Wishlist

All wishlist endpoints require authentication.

### Get Wishlist
```
GET /api/v1/wishlist
```

### Add to Wishlist
```
POST /api/v1/wishlist/items
```

**Request Body:**
```json
{
  "product_id": "int (required)"
}
```

### Remove from Wishlist
```
DELETE /api/v1/wishlist/items/{id}
```

---

## Coupons

### Validate Coupon
```
POST /api/v1/coupons/validate
```

**Request Body:**
```json
{
  "code": "string (required)"
}
```

### Apply Coupon to Cart
```
POST /api/v1/cart/coupon
```

**Requires:** Authentication

**Request Body:**
```json
{
  "code": "string (required)"
}
```

### Remove Coupon from Cart
```
DELETE /api/v1/cart/coupon
```

**Requires:** Authentication

### Get Available Coupons
```
GET /api/v1/user/coupons/available
```

**Requires:** Authentication

---

## Reviews

### Create Review
```
POST /api/v1/products/{id}/reviews
```

**Requires:** Authentication

**Request Body:**
```json
{
  "rating": "int (required, min:1, max:5)",
  "title": "string (optional, max:255)",
  "comment": "string (required, min:10)"
}
```

### Update Review
```
PUT /api/v1/reviews/{id}
```

**Requires:** Authentication (own reviews only)

### Delete Review
```
DELETE /api/v1/reviews/{id}
```

**Requires:** Authentication (own reviews only)

---

## User Profile

All endpoints require authentication.

### Get Current User
```
GET /api/v1/user
```

### Update Profile
```
PUT /api/v1/user/profile
```

**Request Body:**
```json
{
  "first_name": "string (optional)",
  "last_name": "string (optional)",
  "email": "string (optional, email)",
  "phone": "string (optional)"
}
```

### Update Password
```
PUT /api/v1/user/password
```

**Request Body:**
```json
{
  "current_password": "string (required)",
  "password": "string (required, min:8, confirmed)",
  "password_confirmation": "string (required)"
}
```

---

## Admin Endpoints

All admin endpoints require authentication with admin role.

### Dashboard Statistics
```
GET /api/v1/admin/dashboard
```

### Products Management
```
GET    /api/v1/admin/products       # List all products (including inactive)
POST   /api/v1/admin/products       # Create product
GET    /api/v1/admin/products/{id}  # Get product details
PUT    /api/v1/admin/products/{id}  # Update product
DELETE /api/v1/admin/products/{id}  # Delete product
POST   /api/v1/admin/products/{id}/images           # Upload product images
DELETE /api/v1/admin/products/{id}/images/{imageId} # Delete product image
```

### Categories Management
```
GET    /api/v1/admin/categories
POST   /api/v1/admin/categories
GET    /api/v1/admin/categories/{id}
PUT    /api/v1/admin/categories/{id}
DELETE /api/v1/admin/categories/{id}
```

### Orders Management
```
GET /api/v1/admin/orders           # List all orders
GET /api/v1/admin/orders/{id}      # Get order details
PUT /api/v1/admin/orders/{id}/status  # Update order status
```

### Users Management
```
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{id}
PUT    /api/v1/admin/users/{id}
DELETE /api/v1/admin/users/{id}
```

### Coupons Management
```
GET    /api/v1/admin/coupons
POST   /api/v1/admin/coupons
GET    /api/v1/admin/coupons/{id}
PUT    /api/v1/admin/coupons/{id}
DELETE /api/v1/admin/coupons/{id}
PUT    /api/v1/admin/coupons/{id}/toggle      # Toggle active status
GET    /api/v1/admin/coupons/{id}/statistics  # Get usage statistics
```

### Settings
```
GET /api/v1/admin/settings
PUT /api/v1/admin/settings
```

---

## Error Responses

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### Common HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## Authentication

Protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

The token is obtained from login/register responses.

---

## Rate Limiting

- **Auth endpoints:** 60 requests/minute
- **API endpoints:** 60 requests/minute
- **Admin endpoints:** 120 requests/minute

---

## Cart Configuration

| Setting | Value |
|---------|-------|
| Tax Rate | 10% |
| Free Shipping Threshold | $100 |
| Default Shipping Cost | $15 |

---

## Order Status Flow

```
pending → processing → shipped → delivered
    ↓
cancelled
```

## Payment Methods

| Method | Code |
|--------|------|
| Cash on Delivery | `cod` |
| Credit Card | `credit_card` |

---

## Order Number Format

`ORD-YYYYMMDD-XXXXXX`

Example: `ORD-20260228-ABC123`

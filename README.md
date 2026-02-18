# Vanessa Perfumes - Luxury E-Commerce Platform

## Project Overview

A premium luxury perfume brand web application built with **Next.js 15**, **Laravel 12**, and **MySQL**. The architecture follows industry best practices for scalability, security, and maintainability.

## Technology Stack

### Frontend
- **Next.js 15** (App Router, React Server Components)
- **TypeScript** (Strict mode)
- **Tailwind CSS 4** (Custom design system)
- **SWR** (Data fetching & caching)
- **Axios** (HTTP client)

### Backend
- **Laravel 12** (PHP 8.2+)
- **Laravel Sanctum** (API authentication)
- **MySQL 8.0** (Database via XAMPP)
- **Repository Pattern** (Clean architecture)
- **Service Layer** (Business logic abstraction)

### Infrastructure
- **XAMPP** (Local development environment)
- **Git** (Version control)
- **Composer** (PHP dependency management)
- **npm** (Node.js package management)

---

## Project Structure

### Backend (`/backend`)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── BaseController.php
│   │   │       └── V1/
│   │   │           ├── AuthController.php
│   │   │           ├── ProductController.php
│   │   │           ├── CategoryController.php
│   │   │           ├── CartController.php
│   │   │           ├── OrderController.php
│   │   │           ├── UserController.php
│   │   │           └── Admin/
│   │   └── Middleware/
│   │       ├── SecurityHeaders.php
│   │       └── ApiRateLimiter.php
│   ├── Models/
│   ├── Repositories/
│   │   └── ProductRepository.php
│   ├── Services/
│   │   └── ProductService.php
│   └── Exceptions/
│       └── Handler.php
├── config/
│   └── cors.php
├── database/
│   └── migrations/
│       ├── 2025_01_01_000001_create_products_tables.php
│       ├── 2025_01_01_000002_create_user_tables.php
│       └── 2025_01_01_000003_create_orders_tables.php
├── routes/
│   ├── api.php
│   └── web.php
├── .env.example
├── composer.json
└── README.md
```

### Frontend (`/luxury-perfume-frontend`)

```
luxury-perfume-frontend/
├── app/
│   ├── (shop)/                    # Shop route group
│   │   ├── products/
│   │   ├── categories/
│   │   ├── cart/
│   │   ├── checkout/
│   │   └── account/
│   ├── admin/                     # Admin dashboard
│   ├── api/                       # API routes
│   ├── layout.tsx
│   ├── page.tsx
│   └── globals.css
├── components/
│   ├── layout/
│   │   ├── Header.tsx
│   │   ├── Footer.tsx
│   │   └── Navigation.tsx
│   ├── product/
│   │   ├── ProductCard.tsx
│   │   ├── ProductGrid.tsx
│   │   └── ProductDetails.tsx
│   ├── ui/
│   │   ├── Button.tsx
│   │   ├── Input.tsx
│   │   └── Loading.tsx
│   └── cart/
│       ├── CartItem.tsx
│       └── CartSummary.tsx
├── lib/
│   ├── api/
│   │   ├── client.ts
│   │   ├── auth.ts
│   │   ├── products.ts
│   │   ├── cart.ts
│   │   └── orders.ts
│   ├── hooks/
│   │   └── index.ts
│   ├── types/
│   │   └── index.ts
│   └── utils/
│       └── helpers.ts
├── public/
│   └── images/
├── .env.local (create from env.example.txt)
├── next.config.js
├── tsconfig.json
└── package.json
```

---

## Development Environment Setup

### Prerequisites

- **PHP 8.2+**
- **Composer**
- **Node.js 20+**
- **XAMPP** (Apache + MySQL)
- **Git**

### Step 1: Clone Repository

```bash
git clone <repository-url>
cd vanessa
```

### Step 2: Backend Setup (Laravel)

```bash
# Navigate to backend
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure MySQL in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=vanessa_perfumes
# DB_USERNAME=root
# DB_PASSWORD=

# Start XAMPP (Apache & MySQL)
# Create database in phpMyAdmin: vanessa_perfumes

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Start Laravel development server
php artisan serve
```

**Backend URL:** http://localhost:8000

### Step 3: Frontend Setup (Next.js)

```bash
# Navigate to frontend (from project root)
cd luxury-perfume-frontend

# Install dependencies
npm install

# Copy environment file
cp env.example.txt .env.local

# Start development server
npm run dev
```

**Frontend URL:** http://localhost:3000

---

## API Design Patterns

### RESTful API Structure

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/health` | Health check |
| GET | `/api/v1/products` | List products |
| GET | `/api/v1/products/{slug}` | Product details |
| GET | `/api/v1/categories` | List categories |
| POST | `/api/v1/auth/register` | User registration |
| POST | `/api/v1/auth/login` | User login |
| GET | `/api/v1/cart` | Get cart |
| POST | `/api/v1/cart/items` | Add to cart |
| GET | `/api/v1/orders` | List orders |
| POST | `/api/v1/orders` | Create order |

### Response Format

```json
{
  "success": true,
  "message": "Success message",
  "data": { ... },
  "pagination": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 12,
    "total": 120
  }
}
```

---

## Security Best Practices

### Backend Security

1. **Authentication**: Laravel Sanctum for API token authentication
2. **CORS**: Configured for frontend domain only
3. **Rate Limiting**: API rate limiting with customizable limits
4. **Security Headers**: X-Content-Type-Options, X-Frame-Options, CSP
5. **Input Validation**: Form request validation on all endpoints
6. **SQL Injection Prevention**: Query binding and Eloquent ORM
7. **XSS Protection**: Output escaping and CSP headers
8. **CSRF Protection**: Token-based protection for web routes

### Frontend Security

1. **Environment Variables**: Sensitive data in `.env.local`
2. **API Token Storage**: LocalStorage with secure handling
3. **HTTPS**: Enforced in production
4. **Content Security Policy**: Strict CSP rules
5. **Secure Cookies**: HttpOnly and Secure flags

---

## Database Schema

### Core Tables

- **users** - Customer and admin accounts
- **categories** - Product categories (hierarchical)
- **products** - Product catalog
- **product_images** - Product gallery
- **product_variants** - Size/price variants
- **carts** & **cart_items** - Shopping cart
- **orders** & **order_items** - Order management
- **reviews** - Product reviews
- **wishlists** - User wishlists

See migration files in `/backend/database/migrations/` for full schema details.

---

## Frontend Architecture

### Component Hierarchy

```
app/
├── layout.tsx              # Root layout (Header + Footer)
├── page.tsx                # Home page
├── (shop)/
│   ├── layout.tsx          # Shop layout
│   ├── products/
│   │   └── page.tsx        # Product listing
│   └── categories/
│       └── [slug]/
│           └── page.tsx    # Category products
└── admin/
    └── layout.tsx          # Admin dashboard layout
```

### State Management

- **Server State**: SWR for API data caching
- **Client State**: React hooks and context
- **Cart State**: Local storage + API sync

### Styling Strategy

- **Tailwind CSS**: Utility-first approach
- **Custom Theme**: Luxury brand color palette
- **Component Classes**: Reusable `.btn-luxury`, `.input-luxury`
- **Responsive Design**: Mobile-first breakpoints

---

## Coding Standards

### PHP (Laravel)

- **PSR-12** coding standard
- **Strict types** declaration
- **Type hints** for all parameters and returns
- **Dependency injection** via constructor
- **Repository pattern** for data access

### TypeScript (Next.js)

- **Strict mode** enabled
- **Explicit types** for all functions
- **Interface naming**: PascalCase
- **File naming**: kebab-case
- **Component naming**: PascalCase

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `ProductController` |
| Methods | camelCase | `getProductBySlug` |
| Variables | camelCase | `productList` |
| Constants | UPPER_SNAKE_CASE | `API_BASE_URL` |
| Database | snake_case | `product_images` |
| Routes | kebab-case | `/new-arrivals` |

---

## Production Deployment

### Backend Deployment

1. **Environment Configuration**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.vanessaperfumes.com
   ```

2. **Security Settings**
   ```bash
   SESSION_SECURE_COOKIE=true
   SESSION_HTTP_ONLY=true
   ```

3. **Optimization**
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

### Frontend Deployment

1. **Environment Variables**
   ```bash
   NEXT_PUBLIC_API_URL=https://api.vanessaperfumes.com/api/v1
   ```

2. **Build**
   ```bash
   npm run build
   ```

3. **Static Export** (if using static hosting)
   ```javascript
   // next.config.js
   module.exports = {
     output: 'export',
     distDir: 'dist'
   }
   ```

---

## Version Control Workflow

### Git Branching Strategy

```
main        # Production-ready code
├── develop # Integration branch
│   ├── feature/products-api
│   ├── feature/auth-system
│   └── feature/checkout-flow
└── hotfix/security-patch
```

### Commit Message Format

```
type(scope): subject

body (optional)

footer (optional)
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

**Examples:**
```
feat(products): add product filtering by category
fix(auth): resolve token expiration issue
docs(readme): update API endpoint documentation
```

---

## Performance Optimization

### Backend

- **Eager Loading**: Use `with()` to reduce N+1 queries
- **Query Caching**: Cache frequently accessed data
- **Pagination**: Always paginate list endpoints
- **Database Indexing**: Index frequently queried columns
- **Queue Workers**: Process heavy tasks asynchronously

### Frontend

- **Image Optimization**: Next.js Image component with WebP
- **Code Splitting**: Dynamic imports for heavy components
- **Prefetching**: SWR data prefetching
- **Static Generation**: Use `generateStaticParams` for product pages
- **Bundle Analysis**: Monitor bundle size with `@next/bundle-analyzer`

---

## Testing Strategy

### Backend Tests

```bash
# Unit tests
php artisan test

# Feature tests
php artisan test --filter=ProductTest
```

### Frontend Tests

```bash
# Unit tests
npm run test

# E2E tests
npm run test:e2e
```

---

## Troubleshooting

### Common Issues

**CORS Errors**
- Check `FRONTEND_URL` in `.env`
- Verify `config/cors.php` settings

**Database Connection**
- Ensure XAMPP MySQL is running
- Check credentials in `.env`
- Verify database `vanessa_perfumes` exists

**Asset Loading**
- Run `npm run build` in backend
- Check `VITE_APP_NAME` configuration

---

## Support & Resources

- **Laravel Docs**: https://laravel.com/docs
- **Next.js Docs**: https://nextjs.org/docs
- **Tailwind CSS**: https://tailwindcss.com/docs

---

## License

Proprietary - Vanessa Perfumes

---

**Built with passion for luxury fragrance experiences.**

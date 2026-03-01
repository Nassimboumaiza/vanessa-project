# Vanessa Perfumes - Backend API

A premium luxury perfume brand REST API built with **Laravel 12** and **MySQL**.

## Technology Stack

- **Laravel 12** (PHP 8.2+)
- **Laravel Sanctum** (API authentication)
- **MySQL 8.0** (Database)
- **Repository Pattern** (Clean architecture)
- **Service Layer** (Business logic abstraction)

## Project Structure

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
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   └── Exceptions/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
├── .env.example
├── composer.json
└── artisan
```

## Development Environment Setup

### Prerequisites

- **PHP 8.2+**
- **Composer**
- **MySQL 8.0**
- **Docker** (optional, for containerized development)

### Option 1: Docker Setup (Recommended)

```bash
# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Seed database (optional)
docker compose exec app php artisan db:seed
```

**API URL:** http://localhost:8000/api/v1

### Option 2: Local Setup

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

# Create database and run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Start Laravel development server
php artisan serve
```

**API URL:** http://localhost:8000

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | User registration |
| POST | `/api/v1/auth/login` | User login |
| POST | `/api/v1/auth/logout` | User logout |
| GET | `/api/v1/auth/user` | Get current user |

### Products
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/products` | List products |
| GET | `/api/v1/products/{slug}` | Product details |
| GET | `/api/v1/categories` | List categories |
| GET | `/api/v1/categories/{slug}/products` | Products by category |

### Cart
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/cart` | Get cart |
| POST | `/api/v1/cart/items` | Add to cart |
| PUT | `/api/v1/cart/items/{id}` | Update cart item |
| DELETE | `/api/v1/cart/items/{id}` | Remove from cart |

### Orders
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/orders` | List orders |
| POST | `/api/v1/orders` | Create order |
| GET | `/api/v1/orders/{id}` | Order details |

### Health Check
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/health` | Health check |

## Response Format

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

## Security

- **Authentication**: Laravel Sanctum for API token authentication
- **CORS**: Configured for allowed origins
- **Rate Limiting**: API rate limiting enabled
- **Input Validation**: Form request validation on all endpoints
- **SQL Injection Prevention**: Query binding and Eloquent ORM

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ProductTest
```

## Docker Commands

```bash
# Build and start
docker compose up -d --build

# View logs
docker compose logs -f [service]

# Stop all
docker compose down

# Run artisan commands
docker compose exec app php artisan [command]

# Database access
docker compose exec db mysql -u vanessa -p
```

## License

Proprietary - Vanessa Perfumes

---

**Built with passion for luxury fragrance experiences.**

<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// API Versioning - V1 Routes
Route::prefix('v1')->group(function (): void {

    // Health Check
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'check']);
    Route::get('/ping', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'ping']);

    // Public Routes
    Route::get('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
    Route::get('/products/{slug}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
    Route::get('/categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    Route::get('/categories/{slug}/products', [\App\Http\Controllers\Api\V1\CategoryController::class, 'products']);

    // Newsletter Subscription
    Route::post('/newsletter/subscribe', [\App\Http\Controllers\Api\V1\NewsletterController::class, 'subscribe']);

    // Contact Form
    Route::post('/contact', [\App\Http\Controllers\Api\V1\ContactController::class, 'store']);

    // Authentication Routes
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword'])->name('password.reset');
    });

    // Protected Routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

        // User Profile
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::put('/user/profile', [\App\Http\Controllers\Api\V1\UserController::class, 'updateProfile']);
        Route::put('/user/password', [\App\Http\Controllers\Api\V1\UserController::class, 'updatePassword']);

        // Auth
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::post('/auth/refresh', [\App\Http\Controllers\Api\V1\AuthController::class, 'refresh']);

        // Shopping Cart
        Route::get('/cart', [\App\Http\Controllers\Api\V1\CartController::class, 'index']);
        Route::post('/cart/items', [\App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
        Route::put('/cart/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
        Route::delete('/cart/items/{id}', [\App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
        Route::delete('/cart', [\App\Http\Controllers\Api\V1\CartController::class, 'clear']);

        // Wishlist
        Route::get('/wishlist', [\App\Http\Controllers\Api\V1\WishlistController::class, 'index']);
        Route::post('/wishlist/items', [\App\Http\Controllers\Api\V1\WishlistController::class, 'addItem']);
        Route::delete('/wishlist/items/{id}', [\App\Http\Controllers\Api\V1\WishlistController::class, 'removeItem']);

        // Orders
        Route::get('/orders', [\App\Http\Controllers\Api\V1\OrderController::class, 'index']);
        Route::post('/orders', [\App\Http\Controllers\Api\V1\OrderController::class, 'store']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Api\V1\OrderController::class, 'show']);
        Route::get('/orders/{id}/tracking', [\App\Http\Controllers\Api\V1\OrderController::class, 'tracking']);

        // Reviews
        Route::post('/products/{id}/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'store']);
        Route::put('/reviews/{id}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'destroy']);
    });

    // Admin Routes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
        // Dashboard Statistics
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Admin\DashboardController::class, 'index']);

        // Products Management
        Route::apiResource('/products', \App\Http\Controllers\Api\V1\Admin\ProductController::class);
        Route::post('/products/{id}/images', [\App\Http\Controllers\Api\V1\Admin\ProductController::class, 'uploadImages']);
        Route::delete('/products/{id}/images/{imageId}', [\App\Http\Controllers\Api\V1\Admin\ProductController::class, 'deleteImage']);

        // Categories Management
        Route::apiResource('/categories', \App\Http\Controllers\Api\V1\Admin\CategoryController::class);

        // Orders Management
        Route::get('/orders', [\App\Http\Controllers\Api\V1\Admin\OrderController::class, 'index']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Api\V1\Admin\OrderController::class, 'show']);
        Route::put('/orders/{id}/status', [\App\Http\Controllers\Api\V1\Admin\OrderController::class, 'updateStatus']);

        // Users Management
        Route::get('/users', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'index']);
        Route::get('/users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'show']);
        Route::put('/users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'update']);
        Route::delete('/users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'destroy']);

        // Reviews Management
        Route::get('/reviews', [\App\Http\Controllers\Api\V1\Admin\ReviewController::class, 'index']);
        Route::put('/reviews/{id}/approve', [\App\Http\Controllers\Api\V1\Admin\ReviewController::class, 'approve']);
        Route::delete('/reviews/{id}', [\App\Http\Controllers\Api\V1\Admin\ReviewController::class, 'destroy']);

        // Newsletter Subscribers
        Route::get('/subscribers', [\App\Http\Controllers\Api\V1\Admin\NewsletterController::class, 'index']);
        Route::delete('/subscribers/{id}', [\App\Http\Controllers\Api\V1\Admin\NewsletterController::class, 'destroy']);

        // Site Settings
        Route::get('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'index']);
        Route::put('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingController::class, 'update']);
    });
});

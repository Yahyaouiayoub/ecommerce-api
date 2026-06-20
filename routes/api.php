<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\CartController as AdminCartController;

// =========================
// PUBLIC ROUTES
// =========================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{id}', [BrandController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// =========================
// GUEST-ACCESSIBLE ROUTES (cart & orders for guest checkout)
// =========================
// Cart (guests via session_id, users via auth)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'add']);
Route::put('/cart/{id}', [CartController::class, 'update']);
Route::delete('/cart/{id}', [CartController::class, 'remove']);
Route::delete('/cart', [CartController::class, 'clear']);
Route::post('/cart/merge', [CartController::class, 'merge']);

// Orders (guests via session_id, users via auth)
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);

// =========================
// PROTECTED ROUTES (auth:sanctum)
// =========================
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Addresses (authenticated users only)
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);

    // Reviews (authenticated users only)
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/by-order/{orderId}', [PaymentController::class, 'showByOrder']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
});

// =========================
// PUBLIC REVIEW ROUTE
// =========================
Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// =========================
// ADMIN ROUTES
// =========================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'deleteImage']);

    // Categories
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Brands
    Route::post('/brands', [BrandController::class, 'store']);
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

    // Orders
    Route::get('/orders', [OrderController::class, 'adminIndex']);
    Route::get('/orders/{id}', [OrderController::class, 'adminShow']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/financial', [DashboardController::class, 'financial']);
    Route::get('/dashboard/revenue', [DashboardController::class, 'revenue']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'adminIndex']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    Route::post('/invoices/{id}/pay', [InvoiceController::class, 'pay']);
    Route::get('/orders/{id}/invoice-summary', [InvoiceController::class, 'orderSummary']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'adminIndex']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::get('/invoices/{id}/payment-options', [PaymentController::class, 'options']);
    Route::get('/orders/{id}/payment-summary', [PaymentController::class, 'orderPaymentSummary']);

    // Expenses
    Route::get('/expenses/categories', [ExpenseController::class, 'categories']);
    Route::get('/expenses/reports/monthly', [ExpenseController::class, 'monthlyReport']);
    Route::get('/expenses/reports/yearly', [ExpenseController::class, 'yearlyReport']);
    Route::get('/expenses/reports/by-category', [ExpenseController::class, 'byCategoryReport']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

    // Carts
    Route::get('/carts', [AdminCartController::class, 'index']);
    Route::get('/carts/{ownerKey}', [AdminCartController::class, 'show']);
    Route::put('/carts/{ownerKey}/abandon', [AdminCartController::class, 'markAbandoned']);
    Route::post('/carts/{ownerKey}/convert', [AdminCartController::class, 'convertToUser']);
    Route::delete('/carts/{ownerKey}', [AdminCartController::class, 'destroy']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/summary', [AdminUserController::class, 'summary']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
});

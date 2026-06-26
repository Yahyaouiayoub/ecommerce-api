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
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PayPalController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\CartController as AdminCartController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\ShippingMethodController as AdminShippingMethodController;

// =========================
// PUBLIC ROUTES
// =========================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/2fa/verify-login', [AuthController::class, 'verifyTwoFactor']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{id}', [BrandController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/price-range', [ProductController::class, 'priceRange']);
Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);
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
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::get('/sessions', [AuthController::class, 'sessions']);
    Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession']);
    Route::post('/2fa/enable', [\App\Http\Controllers\Api\TwoFactorController::class, 'enable']);
    Route::post('/2fa/confirm', [\App\Http\Controllers\Api\TwoFactorController::class, 'confirm']);
    Route::post('/2fa/disable', [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
    Route::get('/2fa/status', [\App\Http\Controllers\Api\TwoFactorController::class, 'status']);
    Route::post('/2fa/recovery-codes', [\App\Http\Controllers\Api\TwoFactorController::class, 'regenerateRecoveryCodes']);

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

    // Invoices (customer access)
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
});

// Invoice PDF routes — publicly reachable so browser-initiated downloads work.
// Auth is handled inside the controller via Bearer header or ?token= query param.
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'previewPdf']);
Route::get('/invoices/{id}/download', [InvoiceController::class, 'downloadPdf']);

// =========================
// PUBLIC SETTINGS (shipping & tax info — no auth needed)
// =========================
Route::get('/settings/public', [\App\Http\Controllers\Api\SettingsController::class, 'public']);
Route::get('/shipping-methods', [\App\Http\Controllers\Api\ShippingMethodController::class, 'index']);

// =========================
// PUBLIC REVIEW ROUTE
// =========================
// =========================
// PRODUCT VARIANTS (public)
// =========================
Route::get('/products/{productId}/variants', [\App\Http\Controllers\Api\ProductVariantController::class, 'index']);
Route::get('/variants/{id}', [\App\Http\Controllers\Api\ProductVariantController::class, 'show']);

// =========================
// PUBLIC REVIEW ROUTE
// =========================
Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// =========================
// PAYPAL CALLBACKS (no auth — redirect-based)
// =========================
Route::get('/paypal/return', [PayPalController::class, 'returnCallback'])->name('paypal.return');
Route::get('/paypal/cancel', [PayPalController::class, 'cancelCallback'])->name('paypal.cancel');

// =========================
// PAYPAL PAYMENT CREATION (authenticated)
// =========================
Route::middleware('auth:sanctum')->post('/paypal/create', [PayPalController::class, 'createPayment']);

// =========================
// ADMIN ROUTES
// =========================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Products
    Route::get('/products', [ProductController::class, 'adminIndex']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'deleteImage']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'adminIndex']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Product Variants
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::get('/variants/{id}', [ProductVariantController::class, 'show']);
    Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
    Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);

    // Brands
    Route::get('/brands', [BrandController::class, 'adminIndex']);
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
    Route::get('/dashboard/product-profits', [DashboardController::class, 'productProfits']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'adminIndex']);
    Route::get('/invoices/stats', [InvoiceController::class, 'stats']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'adminShow']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::put('/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    Route::post('/invoices/{id}/send', [InvoiceController::class, 'sendPdf']);
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

    // Shipping Methods
    Route::get('/shipping-methods', [AdminShippingMethodController::class, 'index']);
    Route::post('/shipping-methods', [AdminShippingMethodController::class, 'store']);
    Route::put('/shipping-methods/{shippingMethod}', [AdminShippingMethodController::class, 'update']);
    Route::delete('/shipping-methods/{shippingMethod}', [AdminShippingMethodController::class, 'destroy']);

    // PayPal
    Route::post('/paypal/test', [PayPalController::class, 'testConnection']);
    Route::get('/paypal/settings', [PayPalController::class, 'getSettings']);
    Route::post('/paypal/settings', [PayPalController::class, 'saveSettings']);

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
    Route::delete('/settings/logo', [AdminSettingsController::class, 'deleteLogo']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/summary', [AdminUserController::class, 'summary']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
});

// Admin invoice PDF routes — publicly reachable so browser downloads work.
// Auth is handled inside the controller via Bearer header or ?token= query param.
Route::prefix('admin')->group(function () {
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'previewPdf']);
    Route::get('/invoices/{id}/download', [InvoiceController::class, 'downloadPdf']);
});

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
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\CouponCheckController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Api\PromotionController as PublicPromotionController;
use App\Http\Controllers\Api\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\HomepageFeatureController as PublicHomepageFeatureController;
use App\Http\Controllers\Api\Admin\HomepageFeatureController as AdminHomepageFeatureController;
use App\Http\Controllers\Api\FeaturedReviewController as PublicFeaturedReviewController;
use App\Http\Controllers\Api\Admin\FeaturedReviewController as AdminFeaturedReviewController;

// =========================
// PUBLIC AUTH ROUTES
// =========================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/2fa/verify-login', [AuthController::class, 'verifyTwoFactor']);

// Social Authentication (public)
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Api\SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Api\SocialAuthController::class, 'callback']);
Route::get('/auth/providers', [\App\Http\Controllers\Api\SocialAuthController::class, 'providers']);

// Password Reset (public)
Route::post('/forgot-password', [\App\Http\Controllers\Api\ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [\App\Http\Controllers\Api\ResetPasswordController::class, 'reset']);

// Email Verification (public — signed URL from email)
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Api\VerificationController::class, 'verify'])
    ->name('verification.verify');

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
    // Email Verification (authenticated)
    Route::post('/email/resend', [\App\Http\Controllers\Api\VerificationController::class, 'resend']);
    Route::get('/email/status', [\App\Http\Controllers\Api\VerificationController::class, 'status']);
    Route::post('/2fa/enable', [\App\Http\Controllers\Api\TwoFactorController::class, 'enable']);
    Route::post('/2fa/confirm', [\App\Http\Controllers\Api\TwoFactorController::class, 'confirm']);
    Route::post('/2fa/disable', [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
    Route::get('/2fa/status', [\App\Http\Controllers\Api\TwoFactorController::class, 'status']);
    Route::post('/2fa/recovery-codes', [\App\Http\Controllers\Api\TwoFactorController::class, 'regenerateRecoveryCodes']);

    // Social Accounts (authenticated users only)
    Route::get('/social-accounts', [\App\Http\Controllers\Api\SocialAccountController::class, 'index']);
    Route::delete('/social-accounts/{id}', [\App\Http\Controllers\Api\SocialAccountController::class, 'destroy']);

    // Addresses (authenticated users only)
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);

    // Reviews (authenticated users only)
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Check review eligibility
    Route::get('/orders/eligible-for-review/{productId}', [OrderController::class, 'eligibleForReview']);

    // Wishlist (authenticated users only)
    Route::get('/wishlist', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
    Route::post('/wishlist/{product}', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);

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
// COUPON CHECK (public — works for both guests and authenticated users)
// =========================
Route::post('/coupon/check', [CouponCheckController::class, 'check']);

// =========================
// REFUND ROUTES (customer — protected)
// =========================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/refunds', [RefundController::class, 'store']);
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::get('/refunds/{id}', [RefundController::class, 'show']);
    Route::get('/orders/{id}/refundable-items', [RefundController::class, 'refundableItems']);
});

// =========================
// PROMOTIONS (public — no auth needed)
// =========================
Route::get('/promotions/hero-banners', [PublicPromotionController::class, 'heroBanners']);
Route::get('/promotions/announcement-bars', [PublicPromotionController::class, 'announcementBars']);
Route::get('/promotions/all', [PublicPromotionController::class, 'all']);

// =========================
// HOMEPAGE FEATURES (public — no auth needed)
// =========================
Route::get('/homepage-features', [PublicHomepageFeatureController::class, 'index']);

// =========================
// FEATURED REVIEWS (public — no auth needed)
// =========================
Route::get('/featured-reviews', [PublicFeaturedReviewController::class, 'index']);

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
    Route::put('/products/bulk-status', [ProductController::class, 'bulkStatus']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::get('/products/{id}/references', [ProductController::class, 'references']);
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

    // Coupons
    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::get('/coupons/stats', [AdminCouponController::class, 'stats']);
    Route::post('/coupons', [AdminCouponController::class, 'store']);
    Route::get('/coupons/{id}', [AdminCouponController::class, 'show']);
    Route::put('/coupons/{id}', [AdminCouponController::class, 'update']);
    Route::delete('/coupons/{id}', [AdminCouponController::class, 'destroy']);
    Route::put('/coupons/{id}/toggle-active', [AdminCouponController::class, 'toggleActive']);
    Route::put('/coupons/toggle-enabled', [AdminCouponController::class, 'toggleEnabled']);

    // PayPal
    Route::post('/paypal/test', [PayPalController::class, 'testConnection']);
    Route::get('/paypal/settings', [PayPalController::class, 'getSettings']);
    Route::post('/paypal/settings', [PayPalController::class, 'saveSettings']);

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
    Route::delete('/settings/logo', [AdminSettingsController::class, 'deleteLogo']);

    // Mail / SMTP Settings
    Route::get('/settings/mail', [\App\Http\Controllers\Api\Admin\MailSettingsController::class, 'index']);
    Route::put('/settings/mail', [\App\Http\Controllers\Api\Admin\MailSettingsController::class, 'update']);
    Route::post('/settings/mail/test', [\App\Http\Controllers\Api\Admin\MailSettingsController::class, 'test']);

    // PayPal

    // Refunds
    Route::get('/refunds', [AdminRefundController::class, 'index']);
    Route::get('/refunds/stats', [AdminRefundController::class, 'stats']);
    Route::get('/refunds/{id}', [AdminRefundController::class, 'show']);
    Route::put('/refunds/{id}/approve', [AdminRefundController::class, 'approve']);
    Route::put('/refunds/{id}/reject', [AdminRefundController::class, 'reject']);
    Route::put('/refunds/{id}/complete', [AdminRefundController::class, 'complete']);
    Route::put('/refunds/{id}/notes', [AdminRefundController::class, 'updateNotes']);

    // Promotions
    Route::get('/promotions', [AdminPromotionController::class, 'index']);
    Route::get('/promotions/stats', [AdminPromotionController::class, 'stats']);
    Route::get('/promotions/{id}', [AdminPromotionController::class, 'show']);
    Route::post('/promotions', [AdminPromotionController::class, 'store']);
    Route::post('/promotions/{id}', [AdminPromotionController::class, 'update']);
    Route::delete('/promotions/{id}', [AdminPromotionController::class, 'destroy']);
    Route::put('/promotions/{id}/toggle-active', [AdminPromotionController::class, 'toggleActive']);

    // Homepage Features
    Route::get('/homepage-features', [AdminHomepageFeatureController::class, 'index']);
    Route::get('/homepage-features/{id}', [AdminHomepageFeatureController::class, 'show']);
    Route::post('/homepage-features', [AdminHomepageFeatureController::class, 'store']);
    Route::put('/homepage-features/{id}', [AdminHomepageFeatureController::class, 'update']);
    Route::delete('/homepage-features/{id}', [AdminHomepageFeatureController::class, 'destroy']);
    Route::put('/homepage-features/{id}/toggle-active', [AdminHomepageFeatureController::class, 'toggleActive']);
    Route::post('/homepage-features/reorder', [AdminHomepageFeatureController::class, 'reorder']);

    // Featured Reviews
    Route::get('/featured-reviews', [AdminFeaturedReviewController::class, 'index']);
    Route::get('/featured-reviews/{id}', [AdminFeaturedReviewController::class, 'show']);

    // Review Moderation
    Route::get('/reviews', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'index']);
    Route::get('/reviews/{id}', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'show']);
    Route::put('/reviews/{id}/approve', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'approve']);
    Route::put('/reviews/{id}/reject', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'reject']);
    Route::put('/reviews/{id}/pending', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'pending']);
    Route::post('/reviews/bulk-approve', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'bulkApprove']);
    Route::post('/reviews/bulk-reject', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'bulkReject']);
    Route::get('/reviews/stats', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'stats']);
    Route::get('/review-products', [\App\Http\Controllers\Api\Admin\AdminReviewController::class, 'products']);
    Route::put('/featured-reviews/{id}/toggle-featured', [AdminFeaturedReviewController::class, 'toggleFeatured']);
    Route::put('/featured-reviews/{id}/toggle-active', [AdminFeaturedReviewController::class, 'toggleActive']);
    Route::put('/featured-reviews/{id}/order', [AdminFeaturedReviewController::class, 'updateOrder']);
    Route::post('/featured-reviews/reorder', [AdminFeaturedReviewController::class, 'reorder']);
    Route::get('/featured-reviews/stats', [AdminFeaturedReviewController::class, 'stats']);
    Route::get('/featured-review-products', [AdminFeaturedReviewController::class, 'products']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/summary', [AdminUserController::class, 'summary']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    // Social Auth Settings
    Route::get('/settings/social-auth', [\App\Http\Controllers\Api\Admin\SocialAuthSettingsController::class, 'index']);
    Route::put('/settings/social-auth', [\App\Http\Controllers\Api\Admin\SocialAuthSettingsController::class, 'update']);

    // Email Logs
    Route::get('/email-logs', [\App\Http\Controllers\Api\Admin\EmailLogController::class, 'index']);
    Route::get('/email-logs/stats', [\App\Http\Controllers\Api\Admin\EmailLogController::class, 'stats']);

    // Health Check
    Route::get('/health', [\App\Http\Controllers\Api\Admin\HealthCheckController::class, 'index']);
    Route::post('/health/test/{service}', [\App\Http\Controllers\Api\Admin\HealthCheckController::class, 'test']);
});

// Admin invoice PDF routes — publicly reachable so browser downloads work.
// Auth is handled inside the controller via Bearer header or ?token= query param.
Route::prefix('admin')->group(function () {
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'previewPdf']);
    Route::get('/invoices/{id}/download', [InvoiceController::class, 'downloadPdf']);
});

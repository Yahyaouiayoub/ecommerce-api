<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Revenue;
use App\Models\Cart;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Cache TTL in seconds.
     * Stats are cached for 5 minutes, financial data for 10 minutes.
     */
    private const STATS_CACHE_TTL = 300;
    private const FINANCIAL_CACHE_TTL = 600;

    /**
     * Build a time-bucketed cache key so all users share the same cache entry
     * within each 5-minute window.
     */
    private function cacheKey(string $prefix, int $ttl = 300): string
    {
        $bucket = now()->timestamp - (now()->timestamp % $ttl);
        return "dashboard:{$prefix}:{$bucket}";
    }

    /**
     * Get dashboard statistics for admin.
     * Revenue is calculated from paid invoices only (completed/paid sales).
     *
     * Results are cached for 5 minutes to absorb repeated requests
     * from auto-polling and multiple admin users.
     */
    public function stats()
    {
        $data = Cache::remember(
            $this->cacheKey('stats', self::STATS_CACHE_TTL),
            self::STATS_CACHE_TTL,
            fn() => $this->computeStats()
        );

        return response()->json($data);
    }

    /**
     * Compute all dashboard stats from the database.
     * Extracted so the work can be cached independently.
     */
    private function computeStats(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfDay = $now->copy()->startOfDay();

        // =========================
        // REVENUE FROM PAID INVOICES
        // =========================
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('paid_amount');

        $revenueThisMonth = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('paid_amount');

        $revenueToday = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $startOfDay)
            ->sum('paid_amount');

        // =========================
        // REVENUE BY MONTH (last 12 months)
        // =========================
        $revenueByMonth = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('SUM(paid_amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(fn($item) => [
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'total' => (float) $item->total,
            ]);

        // Fill in missing months with zero values
        $revenueByMonth = $this->fillMissingMonths($revenueByMonth, 12);

        // =========================
        // ORDERS BY MONTH (last 12 months)
        // =========================
        $ordersByMonth = Order::where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
                DB::raw('SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing'),
                DB::raw('SUM(CASE WHEN status = "shipped" THEN 1 ELSE 0 END) as shipped')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(fn($item) => [
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'total' => (int) $item->total,
                'delivered' => (int) $item->delivered,
                'pending' => (int) $item->pending,
                'cancelled' => (int) $item->cancelled,
                'processing' => (int) $item->processing,
                'shipped' => (int) $item->shipped,
            ]);

        // Fill in missing months with zero values
        $ordersByMonth = $this->fillMissingMonths($ordersByMonth, 12, true);

        // =========================
        // LEGACY (keep for backward compat)
        // =========================
        $totalRevenueLegacy = Revenue::sum('amount');
        $totalExpenses = Expense::sum('amount');
        $netRevenue = $totalRevenueLegacy - $totalExpenses;

        // =========================
        // CART ANALYTICS
        // =========================
        $activeCarts = Cart::where('status', 'active')->count();
        $abandonedCarts = Cart::where('status', 'abandoned')->count();
        $convertedCarts = Cart::where('status', 'converted')->count();

        // =========================
        // INVOICE STATISTICS
        // =========================
        $totalInvoices = Invoice::count();
        $paidInvoices = Invoice::where('status', 'paid')->count();
        $pendingInvoices = Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending'])->count();
        $refundedInvoices = Invoice::where('status', 'refunded')->count();
        $failedInvoices = Invoice::where('status', 'failed')->count();
        $cancelledInvoices = Invoice::where('status', 'cancelled')->count();
        $totalPendingAmount = (float) Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending'])
            ->get()
            ->sum(fn($inv) => $inv->remaining_amount);

        return [
            // Revenue analytics (from paid invoices)
            'total_revenue' => $totalRevenue,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_today' => $revenueToday,
            'revenue_by_month' => $revenueByMonth,

            // Order counts
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'processing_orders' => Order::where('status', 'processing')->count(),
            'shipped_orders' => Order::where('status', 'shipped')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'orders_by_month' => $ordersByMonth,

            // Product & User stats
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'featured_products' => Product::where('featured', true)->count(),
            'low_stock_products' => Product::where('is_active', true)->where('stock', '>', 0)->where('stock', '<=', 5)->count(),
            'out_of_stock' => Product::where('is_active', true)->where('stock', 0)->count(),
            'total_users' => User::where('role', 'client')->count(),
            'total_admins' => User::where('role', 'admin')->count(),

            // Cart analytics
            'total_carts' => Cart::count(),
            'active_carts' => $activeCarts,
            'abandoned_carts' => $abandonedCarts,
            'converted_carts' => $convertedCarts,

            // Invoice statistics
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'pending_invoices' => $pendingInvoices,
            'refunded_invoices' => $refundedInvoices,
            'failed_invoices' => $failedInvoices,            'cancelled_invoices'   => $cancelledInvoices,
            'total_pending_amount' => $totalPendingAmount,

            // Refund alert
            'refund_alert_count' => $this->computeRefundAlertCount(),

            // Legacy revenue fields (backward compat)
            'total_expenses' => $totalExpenses,
            'net_revenue' => $netRevenue,

            // Legacy orders_by_status
            'orders_by_status' => Order::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),

            // Recent orders
            'recent_orders' => Order::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user?->full_name ?? 'N/A',
                    'total_price' => (float) $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ]),
        ];
    }

    /**
     * Count cancelled orders with paid invoices that haven't been refunded.
     */
    private function computeRefundAlertCount(): int
    {
        return Order::where('status', 'cancelled')
            ->whereHas('invoices', function ($q) {
                $q->where('status', 'paid');
            })
            ->whereDoesntHave('invoices', function ($q) {
                $q->where('status', 'refunded');
            })
            ->count();
    }

    /**
     * Get revenue overview (legacy endpoint).
     */
    public function revenue(Request $request)
    {
        $query = Revenue::query();

        if ($request->has('from')) {
            $query->where('revenue_date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('revenue_date', '<=', $request->to);
        }

        $revenues = $query->latest()->paginate(50);

        return response()->json($revenues);
    }

    /**
     * Get comprehensive financial dashboard data.
     * All metrics are calculated from real database records.
     *
     * Results are cached for 10 minutes since financial data changes infrequently.
     */
    public function financial()
    {
        $data = Cache::remember(
            $this->cacheKey('financial', self::FINANCIAL_CACHE_TTL),
            self::FINANCIAL_CACHE_TTL,
            fn() => $this->computeFinancial()
        );

        return response()->json($data);
    }

    /**
     * Get per-product profit breakdown.
     * Shows each product's purchase cost, units sold, total revenue, total cost, profit, and margin.
     */
    public function productProfits()
    {
        $products = Product::where('is_active', true)->get();

        $results = [];
        foreach ($products as $product) {
            // Total units sold from delivered orders only
            $soldData = OrderItem::where('product_id', $product->id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['delivered', 'shipped']);
                })
                ->selectRaw('COALESCE(SUM(quantity), 0) as total_sold')
                ->selectRaw('COALESCE(SUM(quantity * price), 0) as total_revenue')
                ->first();

            $totalSold = (int) $soldData->total_sold;
            $totalRevenue = (float) $soldData->total_revenue;
            $purchasePrice = (float) ($product->purchase_price ?: 0);
            $totalCost = round($purchasePrice * $totalSold, 2);
            $profit = round($totalRevenue - $totalCost, 2);
            $margin = $totalRevenue > 0 ? round(($profit / $totalRevenue) * 100, 1) : 0;

            // Skip products with zero sales or zero purchase price (not relevant)
            if ($totalSold === 0) continue;

            $results[] = [
                'id'                => $product->id,
                'name'              => $product->name,
                'purchase_price'    => $purchasePrice,
                'selling_price'     => (float) $product->getEffectivePrice(),
                'total_sold'        => $totalSold,
                'total_revenue'     => $totalRevenue,
                'total_cost'        => $totalCost,
                'profit'            => $profit,
                'margin_percentage' => $margin,
            ];
        }

        // Sort by profit descending
        usort($results, fn($a, $b) => $b['profit'] <=> $a['profit']);

        return response()->json([
            'data'        => $results,
            'total_count' => count($results),
            'summary'     => [
                'total_revenue' => round(array_sum(array_column($results, 'total_revenue')), 2),
                'total_cost'    => round(array_sum(array_column($results, 'total_cost')), 2),
                'total_profit'  => round(array_sum(array_column($results, 'profit')), 2),
            ],
        ]);
    }

    /**
     * Compute all financial dashboard data from the database.
     * Extracted so the work can be cached independently.
     */
    private function computeFinancial(): array
    {
        $now = now();

        // =========================
        // DASHBOARD CARDS
        // =========================

        // 1. Total Revenue — sum of paid_amount from paid invoices
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('paid_amount');

        // 2. Total Expenses — sum of amount from all expenses
        $totalExpenses = (float) Expense::sum('amount');

        // 3. Net Profit — Revenue - Expenses
        $netProfit = $totalRevenue - $totalExpenses;

        // 4. Pending Payments — sum of remaining_amount from unpaid/partial/pending/failed invoices
        $pendingPayments = (float) Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending', 'failed'])
            ->get()
            ->sum(fn($inv) => $inv->remaining_amount);

        // 5. Unpaid Invoices — count of invoices not fully paid
        $unpaidInvoicesCount = Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending', 'failed'])->count();

        // =========================
        // CHARTS — Revenue vs Expenses (last 12 months)
        // =========================

        $startDate = $now->copy()->subMonths(11)->startOfMonth();

        // Revenue by month (from paid invoices)
        $revenueByMonth = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $startDate)
            ->select(
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('SUM(paid_amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->keyBy(fn($item) => $item->year . '-' . $item->month);

        // Expenses by month
        $expensesByMonth = Expense::where('expense_date', '>=', $startDate)
            ->select(
                DB::raw('YEAR(expense_date) as year'),
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->keyBy(fn($item) => $item->year . '-' . $item->month);

        // Build combined 12-month array
        $revenueVsExpenses = [];
        $monthlyProfit = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $key = $date->format('Y') . '-' . $date->format('n');
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            $rev = isset($revenueByMonth[$key]) ? (float) $revenueByMonth[$key]->total : 0;
            $exp = isset($expensesByMonth[$key]) ? (float) $expensesByMonth[$key]->total : 0;

            $revenueVsExpenses[] = [
                'year'     => $year,
                'month'    => $month,
                'revenue'  => $rev,
                'expenses' => $exp,
            ];

            $monthlyProfit[] = [
                'year'   => $year,
                'month'  => $month,
                'profit' => round($rev - $exp, 2),
            ];
        }

        // =========================
        // COLLECTION RATE
        // =========================
        $totalInvoices = Invoice::count();
        $paidInvoices = Invoice::where('status', 'paid')->count();
        $collectionRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;

        $collectionRateDetail = [
            'paid_count'     => $paidInvoices,
            'unpaid_count'   => Invoice::where('status', 'unpaid')->count(),
            'partial_count'  => Invoice::where('status', 'partially_paid')->count(),
            'total_count'    => $totalInvoices,
            'rate'           => $collectionRate,
        ];

        return [
            // Cards
            'total_revenue'     => $totalRevenue,
            'total_expenses'    => $totalExpenses,
            'net_profit'        => $netProfit,
            'pending_payments'  => $pendingPayments,
            'unpaid_invoices'   => $unpaidInvoicesCount,

            // Charts
            'revenue_vs_expenses' => $revenueVsExpenses,
            'monthly_profit'      => $monthlyProfit,
            'collection_rate'     => $collectionRateDetail,
        ];
    }

    /**
     * Fill in missing months with zero values for chart consistency.
     */
    private function fillMissingMonths($data, int $count, bool $isOrders = false): array
    {
        $filled = [];
        $now = now();

        for ($i = $count - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            $existing = $data->first(function ($item) use ($year, $month) {
                return $item['year'] === $year && $item['month'] === $month;
            });

            if ($existing) {
                $filled[] = $existing;
            } elseif ($isOrders) {
                $filled[] = [
                    'year' => $year,
                    'month' => $month,
                    'total' => 0,
                    'delivered' => 0,
                    'pending' => 0,
                    'cancelled' => 0,
                    'processing' => 0,
                    'shipped' => 0,
                ];
            } else {
                $filled[] = [
                    'year' => $year,
                    'month' => $month,
                    'total' => 0,
                ];
            }
        }

        return $filled;
    }
}

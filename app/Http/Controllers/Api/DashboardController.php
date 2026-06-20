<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Revenue;
use App\Models\Cart;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for admin.
     * Revenue is calculated from paid invoices only (completed/paid sales).
     */
    public function stats()
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
            ->map(function ($item) {
                return [
                    'year' => (int) $item->year,
                    'month' => (int) $item->month,
                    'total' => (float) $item->total,
                ];
            });

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
            ->map(function ($item) {
                return [
                    'year' => (int) $item->year,
                    'month' => (int) $item->month,
                    'total' => (int) $item->total,
                    'delivered' => (int) $item->delivered,
                    'pending' => (int) $item->pending,
                    'cancelled' => (int) $item->cancelled,
                    'processing' => (int) $item->processing,
                    'shipped' => (int) $item->shipped,
                ];
            });

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

        $stats = [
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
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer' => $order->user?->full_name ?? 'N/A',
                        'total_price' => (float) $order->total_price,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                    ];
                }),
        ];

        return response()->json($stats);
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
     */
    public function financial()
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

        // 4. Pending Payments — sum of remaining_amount from unpaid/partial invoices
        $pendingPayments = (float) Invoice::whereIn('status', ['unpaid', 'partially_paid'])
            ->get()
            ->sum(function ($inv) {
                return $inv->remaining_amount;
            });

        // 5. Unpaid Invoices — count of invoices not fully paid
        $unpaidInvoicesCount = Invoice::whereIn('status', ['unpaid', 'partially_paid'])->count();

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
            ->keyBy(function ($item) {
                return $item->year . '-' . $item->month;
            });

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
            ->keyBy(function ($item) {
                return $item->year . '-' . $item->month;
            });

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

        return response()->json([
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
        ]);
    }

    /**
     * Fill in missing months with zero values for chart consistency.
     */
    private function fillMissingMonths($data, int $count, bool $isOrders = false)
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

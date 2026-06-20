<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    // =========================
    // PRE-DEFINED CATEGORIES
    // =========================
    const CATEGORIES = [
        'salaries',
        'rent',
        'utilities',
        'marketing',
        'supplies',
        'shipping',
        'maintenance',
        'software',
        'insurance',
        'taxes',
        'food',
        'transportation',
        'other',
    ];

    /**
     * Get list of pre-defined categories.
     */
    public function categories()
    {
        return response()->json([
            'data' => self::CATEGORIES,
        ]);
    }

    // =========================
    // LIST EXPENSES
    // =========================
    public function index(Request $request)
    {
        $query = Expense::with('creator')->latest('expense_date');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        // Search by title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $expenses = $query->paginate($request->per_page ?? 20);

        return response()->json($expenses);
    }

    // =========================
    // SHOW SINGLE EXPENSE
    // =========================
    public function show($id)
    {
        $expense = Expense::with('creator')->findOrFail($id);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    // =========================
    // CREATE EXPENSE
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'category'     => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'note'         => 'nullable|string',
            'expense_date' => 'required|date',
        ]);

        $expense = Expense::create([
            'title'        => $request->title,
            'amount'       => $request->amount,
            'category'     => $request->category,
            'description'  => $request->description,
            'note'         => $request->note,
            'expense_date' => $request->expense_date,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Expense created successfully',
            'data'    => new ExpenseResource($expense->load('creator')),
        ], 201);
    }

    // =========================
    // UPDATE EXPENSE
    // =========================
    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'amount'       => 'sometimes|required|numeric|min:0.01',
            'category'     => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'note'         => 'nullable|string',
            'expense_date' => 'sometimes|required|date',
        ]);

        $expense->update($request->only([
            'title', 'amount', 'category', 'description', 'note', 'expense_date',
        ]));

        return response()->json([
            'message' => 'Expense updated successfully',
            'data'    => new ExpenseResource($expense->fresh()->load('creator')),
        ]);
    }

    // =========================
    // DELETE EXPENSE
    // =========================
    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    // =========================
    // REPORTS: MONTHLY EXPENSES
    // =========================
    public function monthlyReport(Request $request)
    {
        $months = $request->input('months', 12);

        $report = Expense::where('expense_date', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->select(
                DB::raw('YEAR(expense_date) as year'),
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'year'  => (int) $item->year,
                    'month' => (int) $item->month,
                    'total' => (float) $item->total,
                    'count' => (int) $item->count,
                ];
            });

        // Fill missing months with zeros
        $filled = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            $existing = $report->first(function ($item) use ($year, $month) {
                return $item['year'] === $year && $item['month'] === $month;
            });

            $filled[] = $existing ?: [
                'year'  => $year,
                'month' => $month,
                'total' => 0,
                'count' => 0,
            ];
        }

        $totals = [
            'total' => (float) Expense::sum('amount'),
            'count' => Expense::count(),
            'average_per_month' => Expense::count() > 0
                ? round(Expense::sum('amount') / max(1, $months), 2)
                : 0,
        ];

        return response()->json([
            'data'   => $filled,
            'totals' => $totals,
        ]);
    }

    // =========================
    // REPORTS: YEARLY EXPENSES
    // =========================
    public function yearlyReport(Request $request)
    {
        $years = $request->input('years', 5);

        $report = Expense::where('expense_date', '>=', now()->subYears($years - 1)->startOfYear())
            ->select(
                DB::raw('YEAR(expense_date) as year'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(amount) as average')
            )
            ->groupBy('year')
            ->orderBy('year', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'year'    => (int) $item->year,
                    'total'   => (float) $item->total,
                    'count'   => (int) $item->count,
                    'average' => round((float) $item->average, 2),
                ];
            });

        // Fill missing years with zeros
        $filled = [];
        for ($i = $years - 1; $i >= 0; $i--) {
            $year = (int) now()->subYears($i)->format('Y');
            $existing = $report->first(function ($item) use ($year) {
                return $item['year'] === $year;
            });
            $filled[] = $existing ?: [
                'year'    => $year,
                'total'   => 0,
                'count'   => 0,
                'average' => 0,
            ];
        }

        return response()->json(['data' => $filled]);
    }

    // =========================
    // REPORTS: BY CATEGORY
    // =========================
    public function byCategoryReport(Request $request)
    {
        $query = Expense::query();

        if ($request->has('year')) {
            $query->whereYear('expense_date', $request->year);
        }
        if ($request->has('month')) {
            $query->whereMonth('expense_date', $request->month);
        }

        $report = $query->select(
                'category',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'category'  => $item->category ?? 'uncategorized',
                    'category_label' => $item->category ? ucfirst($item->category) : 'Uncategorized',
                    'total'     => (float) $item->total,
                    'count'     => (int) $item->count,
                ];
            });

        $grandTotal = $report->sum('total');
        $report = $report->map(function ($item) use ($grandTotal) {
            return array_merge($item, [
                'percentage' => $grandTotal > 0 ? round(($item['total'] / $grandTotal) * 100, 1) : 0,
            ]);
        });

        return response()->json([
            'data'       => $report,
            'grand_total' => $grandTotal,
        ]);
    }
}

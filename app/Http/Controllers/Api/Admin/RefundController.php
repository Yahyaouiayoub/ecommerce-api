<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        private RefundService $refundService
    ) {}

    /**
     * GET /admin/refunds
     * List all refunds with filtering and search.
     */
    public function index(Request $request)
    {
        $query = Refund::with(['user', 'items.orderItem.product', 'order', 'images']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->search($search);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $refunds = $query->latest()->paginate(min($perPage, 100));

        return response()->json($refunds);
    }

    /**
     * GET /admin/refunds/{id}
     * Show a single refund with full details.
     */
    public function show($id)
    {
        $refund = Refund::with([
            'user',
            'items.orderItem.product',
            'order.items.product',
            'order.address',
            'order.user',
            'images',
        ])->findOrFail($id);

        return response()->json($refund);
    }

    /**
     * PUT /admin/refunds/{id}/approve
     * Approve a pending refund request.
     */
    public function approve($id)
    {
        $refund = Refund::findOrFail($id);

        try {
            $refund = $this->refundService->approve($refund);
            return response()->json([
                'message' => 'Refund approved successfully.',
                'refund'  => $refund,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /admin/refunds/{id}/reject
     * Reject a pending refund request.
     */
    public function reject($id, Request $request)
    {
        $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $refund = Refund::findOrFail($id);

        try {
            $refund = $this->refundService->reject($refund, $request->reason ?? '');
            return response()->json([
                'message' => 'Refund rejected.',
                'refund'  => $refund,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /admin/refunds/{id}/complete
     * Mark a refund as completed (processed/paid out).
     */
    public function complete($id)
    {
        $refund = Refund::findOrFail($id);

        try {
            $refund = $this->refundService->complete($refund);
            return response()->json([
                'message' => 'Refund completed successfully.',
                'refund'  => $refund,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /admin/refunds/{id}/notes
     * Update internal notes on a refund.
     */
    public function updateNotes($id, Request $request)
    {
        $request->validate([
            'notes' => 'required|string|max:5000',
        ]);

        $refund = Refund::findOrFail($id);
        $refund = $this->refundService->updateNotes($refund, $request->notes);

        return response()->json([
            'message' => 'Notes updated successfully.',
            'refund'  => $refund,
        ]);
    }

    /**
     * GET /admin/refunds/stats
     * Get refund statistics.
     */
    public function stats()
    {
        $stats = $this->refundService->getDashboardStats();

        return response()->json($stats);
    }
}

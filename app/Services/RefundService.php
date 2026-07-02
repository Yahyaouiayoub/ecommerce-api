<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\RefundImage;
use App\Models\User;
use App\Mail\RefundStatusMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class RefundService
{
    // =========================
    // VALID STATUS TRANSITIONS
    // =========================
    private const VALID_TRANSITIONS = [
        'pending'   => ['approved', 'rejected'],
        'approved'  => ['completed', 'rejected'],
        'rejected'  => [],  // terminal
        'completed' => [],  // terminal
    ];

    /**
     * Validate that a status transition is allowed.
     */
    public function isValidTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Calculate the maximum refundable amount for an order.
     * Only delivered/paid orders can be refunded.
     */
    public function getMaxRefundableAmount(Order $order): float
    {
        $totalPaid = (float) $order->total_price;

        // Subtract already refunded amounts
        $alreadyRefunded = (float) Refund::where('order_id', $order->id)
            ->whereIn('status', ['approved', 'completed'])
            ->sum('refund_amount');

        return round(max(0, $totalPaid - $alreadyRefunded), 2);
    }

    /**
     * Get refundable items from an order (only delivered orders).
     */
    public function getRefundableItems(Order $order): array
    {
        if (!in_array($order->status, ['delivered', 'shipped', 'processing'])) {
            return [];
        }

        $items = $order->items()->with('product')->get()->toArray();

        // Subtract quantities already refunded
        $alreadyRefundedItems = RefundItem::whereHas('refund', function ($q) use ($order) {
            $q->where('order_id', $order->id)
              ->whereIn('status', ['approved', 'completed', 'pending']);
        })->select('order_item_id', 'quantity')->get()
          ->groupBy('order_item_id');

        return array_map(function ($item) use ($alreadyRefundedItems) {
            $refundedQty = 0;
            if ($alreadyRefundedItems->has($item['id'])) {
                $refundedQty = $alreadyRefundedItems[$item['id']]->sum('quantity');
            }
            $item['refundable_quantity'] = max(0, $item['quantity'] - $refundedQty);
            $item['max_refund_amount'] = round($item['price'] * $item['refundable_quantity'], 2);
            return $item;
        }, $items);
    }

    /**
     * Create a refund request.
     */
    public function createRefund(
        Order $order,
        ?User $user,
        string $reason,
        ?string $description,
        array $items,      // [['order_item_id' => 1, 'quantity' => 1], ...]
        float $totalAmount,
        ?array $images = [], // UploadedFile[]
        ?string $guestEmail = null,
        ?string $guestName = null
    ): Refund {
        return DB::transaction(function () use ($order, $user, $reason, $description, $items, $totalAmount, $images, $guestEmail, $guestName) {
            $refund = Refund::create([
                'order_id'      => $order->id,
                'user_id'       => $user?->id,
                'refund_number' => Refund::generateRefundNumber(),
                'status'        => 'pending',
                'reason'        => $reason,
                'description'   => $description,
                'refund_amount' => $totalAmount,
                'guest_email'   => $guestEmail,
                'guest_name'    => $guestName,
            ]);

            // Create refund items
            foreach ($items as $item) {
                $orderItem = OrderItem::findOrFail($item['order_item_id']);
                $itemAmount = round($orderItem->price * $item['quantity'], 2);

                RefundItem::create([
                    'refund_id'     => $refund->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity'      => $item['quantity'],
                    'amount'        => $itemAmount,
                ]);
            }

            // Store uploaded images
            if ($images) {
                foreach ($images as $image) {
                    $path = $image->store('refunds/' . $refund->id, 'public');
                    RefundImage::create([
                        'refund_id'   => $refund->id,
                        'image_path'  => $path,
                    ]);
                }
            }

            $refund->load(['items.orderItem.product', 'images']);

            return $refund;
        });
    }

    /**
     * Approve a refund request.
     */
    public function approve(Refund $refund): Refund
    {
        if (!$this->isValidTransition($refund->status, 'approved')) {
            throw new \InvalidArgumentException("Cannot approve a refund with status '{$refund->status}'.");
        }

        $refund->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        $this->sendStatusNotification($refund);

        return $refund->fresh()->load(['items.orderItem.product', 'images', 'order', 'user']);
    }

    /**
     * Reject a refund request.
     */
    public function reject(Refund $refund, string $reason = ''): Refund
    {
        if (!$this->isValidTransition($refund->status, 'rejected')) {
            throw new \InvalidArgumentException("Cannot reject a refund with status '{$refund->status}'.");
        }

        $data = [
            'status'      => 'rejected',
            'rejected_at' => now(),
        ];

        if ($reason) {
            $data['internal_notes'] = $reason;
        }

        $refund->update($data);

        $this->sendStatusNotification($refund);

        return $refund->fresh()->load(['items.orderItem.product', 'images', 'order', 'user']);
    }

    /**
     * Complete a refund (mark as processed/paid out).
     */
    public function complete(Refund $refund): Refund
    {
        if (!$this->isValidTransition($refund->status, 'completed')) {
            throw new \InvalidArgumentException("Cannot complete a refund with status '{$refund->status}'.");
        }

        DB::transaction(function () use ($refund) {
            $refund->update([
                'status'         => 'completed',
                'completed_at'   => now(),
            ]);

            // Update the order's refund tracking
            $order = $refund->order;
            $totalRefunded = (float) Refund::where('order_id', $order->id)
                ->whereIn('status', ['approved', 'completed'])
                ->sum('refund_amount');

            $order->update([
                'refund_status' => 'partially_refunded',
                'refund_amount' => $totalRefunded,
            ]);

            // If fully refunded, mark as such
            if ($totalRefunded >= (float) $order->total_price) {
                $order->update(['refund_status' => 'fully_refunded']);
            }
        });

        $this->sendStatusNotification($refund);

        return $refund->fresh()->load(['items.orderItem.product', 'images', 'order', 'user']);
    }

    /**
     * Update internal notes on a refund.
     */
    public function updateNotes(Refund $refund, string $notes): Refund
    {
        $refund->update(['internal_notes' => $notes]);
        return $refund->fresh();
    }

    /**
     * Send email notification about refund status change.
     */
    private function sendStatusNotification(Refund $refund): void
    {
        $recipientEmail = $refund->requester_email;

        if (!$recipientEmail) {
            return;
        }

        try {
            Mail::to($recipientEmail)->send(new RefundStatusMail($refund));
        } catch (\Exception $e) {
            // Log the error but don't break the flow
            logger()->error('Failed to send refund status email: ' . $e->getMessage(), [
                'refund_id' => $refund->id,
                'status'    => $refund->status,
            ]);
        }
    }

    /**
     * Get refund statistics for dashboard.
     */
    public function getDashboardStats(): array
    {
        $totalRefunds = Refund::count();
        $pendingRefunds = Refund::where('status', 'pending')->count();
        $approvedRefunds = Refund::where('status', 'approved')->count();
        $rejectedRefunds = Refund::where('status', 'rejected')->count();
        $completedRefunds = Refund::where('status', 'completed')->count();
        $totalRefundedAmount = (float) Refund::whereIn('status', ['approved', 'completed'])->sum('refund_amount');

        // Top 5 most refunded products
        $topRefundedProducts = RefundItem::selectRaw('order_item_id, SUM(quantity) as total_qty, SUM(amount) as total_amount')
            ->whereHas('refund', function ($q) {
                $q->whereIn('status', ['approved', 'completed']);
            })
            ->groupBy('order_item_id')
            ->orderByDesc('total_amount')
            ->with('orderItem.product')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'product_name' => $item->orderItem?->product?->name ?? 'N/A',
                'total_qty'    => (int) $item->total_qty,
                'total_amount' => (float) $item->total_amount,
            ]);

        return [
            'total_refunds'          => $totalRefunds,
            'pending_refunds'        => $pendingRefunds,
            'approved_refunds'       => $approvedRefunds,
            'rejected_refunds'       => $rejectedRefunds,
            'completed_refunds'      => $completedRefunds,
            'total_refunded_amount'  => $totalRefundedAmount,
            'top_refunded_products'  => $topRefundedProducts,
        ];
    }
}

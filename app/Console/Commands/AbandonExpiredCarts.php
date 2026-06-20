<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AbandonExpiredCarts extends Command
{
    protected $signature = 'carts:abandon-expired
                            {--days=7 : Number of days of inactivity before marking a cart as abandoned}
                            {--dry-run : Preview which carts would be abandoned without making changes}';

    protected $description = 'Automatically mark active carts as abandoned after a period of inactivity and send notifications';

    private int $abandonedCount = 0;
    private int $notifiedCount = 0;
    private int $guestCount = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Scanning for active carts inactive since {$cutoff->toDateTimeString()}...");
        $this->newLine();

        if ($dryRun) {
            $this->warn(' DRY-RUN MODE — no changes will be made');
            $this->newLine();
        }

        // ──────────────────────────────────────────────
        // 1. Find expired user carts (logged-in users)
        // ──────────────────────────────────────────────
        $expiredUserGroups = Cart::select('user_id', DB::raw('MAX(updated_at) as last_activity'))
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('last_activity', '<', $cutoff)
            ->get();

        foreach ($expiredUserGroups as $group) {
            $this->processCartGroup($group->user_id, null, $dryRun, $cutoff);
        }

        // ──────────────────────────────────────────────
        // 2. Find expired guest carts (session-based)
        // ──────────────────────────────────────────────
        $expiredGuestGroups = Cart::select('session_id', DB::raw('MAX(updated_at) as last_activity'))
            ->where('status', 'active')
            ->whereNull('user_id')
            ->whereNotNull('session_id')
            ->groupBy('session_id')
            ->having('last_activity', '<', $cutoff)
            ->get();

        foreach ($expiredGuestGroups as $group) {
            $this->processCartGroup(null, $group->session_id, $dryRun, $cutoff);
        }

        // ──────────────────────────────────────────────
        // Summary
        // ──────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Carts marked abandoned', $this->abandonedCount],
                ['Notification emails sent', $this->notifiedCount],
                ['Guest carts (no email)', $this->guestCount],
            ]
        );

        if ($this->abandonedCount === 0) {
            $this->info('No expired carts found.');
        }

        return Command::SUCCESS;
    }

    /**
     * Process a single cart group (user or session).
     */
    private function processCartGroup(?int $userId, ?string $sessionId, bool $dryRun, \Illuminate\Support\Carbon $cutoff): void
    {
        // Build the query for this group's items
        $query = Cart::where('status', 'active');

        if ($userId) {
            $query->where('user_id', $userId);
            $ownerKey = "user_{$userId}";
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
            $ownerKey = "session_{$sessionId}";
        } else {
            return;
        }

        $items = $query->with('product')->get();

        if ($items->isEmpty()) {
            return;
        }

        // Determine who owns this cart
        $cartOwner = $userId ? User::find($userId) : null;
        $customerName = $cartOwner
            ? ($cartOwner->full_name ?? "{$cartOwner->first_name} {$cartOwner->last_name}")
            : 'Guest';
        $itemCount = $items->sum('quantity');
        $lastActivity = $items->max('updated_at');

        $label = $cartOwner
            ? "User #{$userId} ({$customerName})"
            : "Session {$sessionId} (Guest)";

        if ($dryRun) {
            $this->dryRunLine("  [DRY-RUN] Would abandon: {$label} — {$itemCount} item(s), last activity: {$lastActivity->diffForHumans()}");
            $this->abandonedCount++;
            if ($cartOwner?->email) {
                $this->notifiedCount++;
            } else {
                $this->guestCount++;
            }
            return;
        }

        // Mark all items as abandoned
        Cart::where(function ($q) use ($userId, $sessionId) {
            if ($userId) {
                $q->where('user_id', $userId);
            } else {
                $q->where('session_id', $sessionId);
            }
        })
        ->where('status', 'active')
        ->update(['status' => 'abandoned']);

        $this->abandonedCount++;
        $this->verboseLine("  ✓ Abandoned: {$label} — {$itemCount} item(s)");

        // Send notification email
        $this->sendNotification($ownerKey, $items, $cartOwner);
    }

    /**
     * Send abandoned cart notification.
     */
    private function sendNotification(string $ownerKey, $items, ?User $cartOwner): void
    {
        try {
            $mail = new AbandonedCartMail($ownerKey, $items, $cartOwner);
            $recipients = [];

            // Send to cart owner if they have an email
            if ($cartOwner && $cartOwner->email) {
                $recipients[] = $cartOwner->email;
                Mail::to($cartOwner->email)->send($mail);
                $this->notifiedCount++;
            } else {
                $this->guestCount++;
            }

            // Also notify admin
            $adminEmail = config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send($mail);
            }

            if (!empty($recipients)) {
                $this->verboseLine("  📧 Notification sent to: " . implode(', ', $recipients));
            }

        } catch (\Exception $e) {
            $this->warn("  ⚠ Notification failed: {$e->getMessage()}");
        }
    }

    /**
     * Output a line only when running with verbose flag (-v).
     */
    private function verboseLine(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->line($message);
        }
    }

    /**
     * Output a dry-run line (always shown in dry-run mode, visible to user).
     */
    private function dryRunLine(string $message): void
    {
        // In dry-run mode, always show these (they are the main output)
        $this->line($message);
    }
}

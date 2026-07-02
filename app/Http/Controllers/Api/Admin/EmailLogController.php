<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    /**
     * Paginated list of email delivery logs.
     */
    public function index(Request $request)
    {
        $query = EmailLog::latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by recipient
        if ($request->filled('recipient')) {
            $query->where('recipient_email', 'like', '%' . $request->recipient . '%');
        }

        // Filter by mailable type
        if ($request->filled('mailable')) {
            $query->where('mailable_type', $request->mailable);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min((int) ($request->per_page ?? 25), 100);

        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Get summary statistics for email delivery.
     */
    public function stats()
    {
        $totalSent  = EmailLog::where('status', 'sent')->count();
        $totalFailed = EmailLog::where('status', 'failed')->count();

        // Emails sent today
        $todaySent = EmailLog::where('status', 'sent')
            ->whereDate('created_at', today())
            ->count();

        // Most recent sends by type
        $byMailable = EmailLog::selectRaw('mailable_type, COUNT(*) as count')
            ->whereNotNull('mailable_type')
            ->groupBy('mailable_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'total_sent'   => $totalSent,
            'total_failed' => $totalFailed,
            'today_sent'   => $todaySent,
            'by_mailable'  => $byMailable,
        ]);
    }
}

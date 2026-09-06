<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExotelWebhookController extends Controller
{
    /**
     * Exotel's StatusCallback has no documented signature scheme (unlike
     * Razorpay/Stripe). Mitigated by: an app-appended shared-secret query
     * token, plus only ever updating a CallSid we already logged ourselves
     * -- an unknown/forged SID can't create or corrupt a real record.
     */
    public function __invoke(Request $request)
    {
        $secret = trim((string) AppSetting::getValue('exotel_webhook_secret', ''));
        if ($secret !== '' && $request->query('token') !== $secret) {
            Log::warning('Exotel webhook rejected: bad token.');

            return response()->json(['success' => false], 403);
        }

        $callSid = $request->input('CallSid');
        if (! $callSid) {
            return response()->json(['success' => false, 'message' => 'Missing CallSid.'], 422);
        }

        $log = CallLog::where('exotel_call_sid', $callSid)->first();
        if (! $log) {
            // Not one of ours (or the CustomField correlation hasn't matched
            // a call we logged yet) -- ignore rather than create a row from
            // unauthenticated input.
            return response()->json(['success' => true, 'processed' => false]);
        }

        $status = strtolower((string) $request->input('Status', $log->status));
        $log->update([
            'status' => $status,
            'duration_seconds' => $request->input('Duration') !== null ? (int) $request->input('Duration') : $log->duration_seconds,
            'recording_url' => $request->input('RecordingUrl', $log->recording_url),
            'answered_at' => $status === 'in-progress' && ! $log->answered_at ? now() : $log->answered_at,
            'completed_at' => in_array($status, ['completed', 'failed', 'busy', 'no-answer'], true) ? now() : $log->completed_at,
            'raw_webhook_payload' => $request->all(),
        ]);

        return response()->json(['success' => true, 'processed' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\MarketingContact;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmailTrackingController extends Controller
{
    public function open(EmailLog $emailLog): Response
    {
        if (! $emailLog->opened_at) {
            $emailLog->forceFill([
                'status' => EmailLog::STATUS_OPENED,
                'opened_at' => now(),
            ])->save();
        }

        return response(base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => '43',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function unsubscribe(MarketingContact $marketingContact, string $token): View
    {
        abort_unless(hash_equals((string) $marketingContact->unsubscribe_token, $token), 404);

        if ($marketingContact->status !== MarketingContact::STATUS_UNSUBSCRIBED) {
            $marketingContact->forceFill([
                'status' => MarketingContact::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ])->save();
        }

        return view('email.unsubscribed', [
            'contact' => $marketingContact,
        ]);
    }
}

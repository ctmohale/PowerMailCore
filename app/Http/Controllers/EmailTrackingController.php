<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Response;

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
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the task scheduler on hosts without cron (such as Render's free plan). An external
 * timer, a Cloudflare Worker cron trigger, calls this every minute with the shared token.
 */
class CronController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = (string) config('ticketflow.cron_token');

        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 403);

        Artisan::call('schedule:run');

        return response('ok');
    }
}

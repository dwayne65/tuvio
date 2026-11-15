<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Serves uploads kept in the database by the "database" filesystem driver. */
class MediaController extends Controller
{
    public function show(string $path): Response
    {
        $file = DB::table('stored_files')->where('path', $path)->where('visibility', 'public')
            ->first(['contents', 'mime_type', 'size', 'updated_at']);

        abort_if($file === null, 404);

        // Stored names are random and never reused (a replaced cover gets a new name), so
        // browsers and Cloudflare may keep a copy for a long time.
        return response((string) base64_decode($file->contents, true), 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            // Uploads are validated as images; sandboxing stops anything else from running.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

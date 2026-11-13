<?php

namespace App\Http\Controllers;

use App\Domain\Ussd\UssdMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UssdController extends Controller
{
    public function __construct(private UssdMenu $menu) {}

    /** The USSD gateway posts every keypress here and shows our reply on the caller's handset. */
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = $request->validate([
            'sessionid' => ['required', 'string', 'max:100'],
            'msisdn' => ['required', 'string', 'regex:/^[0-9]{9,15}$/'],
            'state' => ['required', 'string'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        $reply = $this->menu->handle($data);

        return response()->json([
            'msisdn' => $data['msisdn'],
            'sessionid' => $data['sessionid'],
            'state' => $reply->state,
            'message' => $reply->message,
        ]);
    }

    /**
     * The gateway signs the raw body: a base64 RSA PKCS#1 v1.5 signature of its SHA-256 digest.
     * Without a configured key, unsigned requests are only accepted in local development.
     */
    private function hasValidSignature(Request $request): bool
    {
        $publicKey = config('ticketflow.ussd.public_key');
        if (! $publicKey) {
            return app()->isLocal();
        }

        $signature = base64_decode((string) $request->header('Signature'), true);

        return $signature !== false
            && openssl_verify($request->getContent(), $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}

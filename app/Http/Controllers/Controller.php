<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use PDOException;
use RuntimeException;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * The message to show a customer for a failed action. Domain errors explain themselves,
     * but database errors (also RuntimeExceptions) would leak SQL and server details, so
     * those are reported and replaced with a generic message.
     */
    protected function customerMessage(RuntimeException $exception): string
    {
        if ($exception instanceof PDOException) {
            report($exception);

            return 'Something went wrong on our side. If you were charged, your payment is safe and we will sort it out — please contact support.';
        }

        return $exception->getMessage();
    }
}

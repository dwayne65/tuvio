<?php

namespace App\Support;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;
use PDOException;

/**
 * With persistent connections, PHP hands back the connection a worker kept from an earlier
 * request without checking it still works. After a network drop or a database restart that
 * connection is dead, and Laravel's first setup statement fails while connecting, which is
 * outside Laravel's usual reconnect-and-retry. PHP marks the connection dead on that failure,
 * so connecting once more opens a fresh one instead of failing the visitor's request.
 */
class ResilientPostgresConnector extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): PDO
    {
        try {
            return parent::connect($config);
        } catch (PDOException $e) {
            if (! $this->causedByLostConnection($e)) {
                throw $e;
            }

            return parent::connect($config);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Checks whether a TCP port is in use on the host machine — not inside this
 * container. Connects out to $hostGateway (host.docker.internal by default,
 * see docker-compose.yml's extra_hosts), which resolves to the Docker host.
 */
final class PortChecker
{
    public function __construct(
        private readonly string $hostGateway,
    ) {
    }

    public function isOnline(int $port): bool
    {
        $connection = @fsockopen($this->hostGateway, $port, $errno, $errstr, 0.5);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}

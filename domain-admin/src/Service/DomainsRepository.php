<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;
use RuntimeException;

final class DomainsRepository
{
    public function __construct(
        private readonly string $domainsConfigPath,
    ) {
    }

    /**
     * @return array<string, int> domain => port, sorted by domain
     */
    public function all(): array
    {
        $map = $this->read();
        ksort($map);

        return $map;
    }

    public function add(string $domain, int $port): void
    {
        $this->write(static function (array $map) use ($domain, $port): array {
            if (\array_key_exists($domain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" already exists.', $domain));
            }

            $map[$domain] = $port;

            return $map;
        });
    }

    public function update(string $currentDomain, string $newDomain, int $port): void
    {
        $this->write(static function (array $map) use ($currentDomain, $newDomain, $port): array {
            if (!\array_key_exists($currentDomain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" does not exist.', $currentDomain));
            }

            if ($newDomain !== $currentDomain && \array_key_exists($newDomain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" already exists.', $newDomain));
            }

            unset($map[$currentDomain]);
            $map[$newDomain] = $port;

            return $map;
        });
    }

    public function remove(string $domain): void
    {
        $this->write(static function (array $map) use ($domain): array {
            if (!\array_key_exists($domain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" does not exist.', $domain));
            }

            unset($map[$domain]);

            return $map;
        });
    }

    /**
     * @return array<string, int>
     */
    private function read(): array
    {
        if (!is_file($this->domainsConfigPath)) {
            return [];
        }

        $raw = file_get_contents($this->domainsConfigPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $map = json_decode($raw, true);
        if (!\is_array($map)) {
            return [];
        }

        $result = [];
        foreach ($map as $domain => $port) {
            $result[(string) $domain] = self::toPort($port);
        }

        return $result;
    }

    /**
     * @param callable(array<string, int>): array<string, int> $mutator
     */
    private function write(callable $mutator): void
    {
        $dir = \dirname($this->domainsConfigPath);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf('Could not create directory "%s".', $dir));
        }

        $handle = fopen($this->domainsConfigPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException(\sprintf('Could not open "%s" for writing.', $this->domainsConfigPath));
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                throw new RuntimeException('Could not acquire lock on domains config file.');
            }

            $raw = stream_get_contents($handle);
            $map = [];
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (\is_array($decoded)) {
                    foreach ($decoded as $domain => $port) {
                        $map[(string) $domain] = self::toPort($port);
                    }
                }
            }

            $map = $mutator($map);
            ksort($map);

            $json = json_encode($map, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n";

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);
        } finally {
            flock($handle, \LOCK_UN);
            fclose($handle);
        }
    }

    private static function toPort(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }
}

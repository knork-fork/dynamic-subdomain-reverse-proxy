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
     * @return array<string, array{port: int, comment: string}> domain => {port, comment}, sorted by domain
     */
    public function all(): array
    {
        $map = $this->read();
        ksort($map);

        return $map;
    }

    public function add(string $domain, int $port, string $comment): void
    {
        $this->write(static function (array $map) use ($domain, $port, $comment): array {
            if (\array_key_exists($domain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" already exists.', $domain));
            }

            $map[$domain] = ['port' => $port, 'comment' => $comment];

            return $map;
        });
    }

    public function update(string $currentDomain, string $newDomain, int $port, string $comment): void
    {
        $this->write(static function (array $map) use ($currentDomain, $newDomain, $port, $comment): array {
            if (!\array_key_exists($currentDomain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" does not exist.', $currentDomain));
            }

            if ($newDomain !== $currentDomain && \array_key_exists($newDomain, $map)) {
                throw new InvalidArgumentException(\sprintf('Domain "%s" already exists.', $newDomain));
            }

            unset($map[$currentDomain]);
            $map[$newDomain] = ['port' => $port, 'comment' => $comment];

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
     * @return array<string, array{port: int, comment: string}>
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
        foreach ($map as $domain => $entry) {
            $result[(string) $domain] = self::toEntry($entry);
        }

        return $result;
    }

    /**
     * @param callable(array<string, array{port: int, comment: string}>): array<string, array{port: int, comment: string}> $mutator
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
                    foreach ($decoded as $domain => $entry) {
                        $map[(string) $domain] = self::toEntry($entry);
                    }
                }
            }

            $map = $mutator($map);
            ksort($map);

            $json = json_encode(self::toStorable($map), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n";

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);
        } finally {
            flock($handle, \LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array{port: int, comment: string}
     */
    private static function toEntry(mixed $value): array
    {
        if (\is_array($value)) {
            return [
                'port' => self::toPort($value['port'] ?? null),
                'comment' => \is_string($value['comment'] ?? null) ? $value['comment'] : '',
            ];
        }

        return ['port' => self::toPort($value), 'comment' => ''];
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

    /**
     * Entries without a comment are stored as a plain port number, to stay
     * backward-compatible with tools consuming the file (e.g. the proxy resolver)
     * and to avoid needlessly rewriting untouched entries.
     *
     * @param array<string, array{port: int, comment: string}> $map
     *
     * @return array<string, int|array{port: int, comment: string}>
     */
    private static function toStorable(array $map): array
    {
        $result = [];
        foreach ($map as $domain => $entry) {
            $result[$domain] = $entry['comment'] === '' ? $entry['port'] : $entry;
        }

        return $result;
    }
}

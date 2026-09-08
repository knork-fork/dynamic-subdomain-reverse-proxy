<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Appends a cache-busting query string (the file's mtime) to static asset
 * URLs, so a deploy is never masked by a CDN or browser caching the old
 * /js/app.js or /css/app.css by URL.
 */
final class AssetVersionExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_version', $this->version(...)),
        ];
    }

    public function version(string $relativePath): string
    {
        $path = $this->publicDir . '/' . ltrim($relativePath, '/');
        $mtime = @filemtime($path);

        return $relativePath . '?v=' . ($mtime !== false ? $mtime : '0');
    }
}

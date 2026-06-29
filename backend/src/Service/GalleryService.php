<?php

namespace App\Service;

/**
 * Reads selectable images from public/gallery. The directory may contain legacy
 * .bmp portraits (e.g. DSA creature art) which browsers render inconsistently, so
 * gallery images are served through GalleryController which converts .bmp -> PNG
 * on the fly. .png/.jpg/.jpeg are served as-is.
 */
class GalleryService
{
    /** Extensions we expose in the gallery. */
    public const ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'bmp'];

    public function __construct(
        private readonly string $galleryDir,
    ) {
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function list(): array
    {
        if (!is_dir($this->galleryDir)) {
            return [];
        }

        $items = [];
        foreach (scandir($this->galleryDir) ?: [] as $entry) {
            if ('.' === $entry[0]) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, \PATHINFO_EXTENSION));
            if (!\in_array($ext, self::ALLOWED_EXT, true)) {
                continue;
            }
            if (!is_file($this->galleryDir.'/'.$entry)) {
                continue;
            }
            $items[] = ['name' => $entry, 'url' => $this->publicUrl($entry)];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * Validate a gallery filename and return its absolute path, or null if invalid.
     * Guards against path traversal (basename only) and disallowed extensions.
     */
    public function resolvePath(string $name): ?string
    {
        if ('' === $name || basename($name) !== $name) {
            return null;
        }
        $ext = strtolower(pathinfo($name, \PATHINFO_EXTENSION));
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            return null;
        }
        $full = $this->galleryDir.'/'.$name;

        return is_file($full) ? $full : null;
    }

    /** The renderable URL stored on entities for a chosen gallery image. */
    public function publicUrl(string $name): string
    {
        return '/api/gallery/image?name='.rawurlencode($name);
    }
}

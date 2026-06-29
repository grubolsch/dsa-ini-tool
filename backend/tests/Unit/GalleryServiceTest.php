<?php

namespace App\Tests\Unit;

use App\Service\GalleryService;
use PHPUnit\Framework\TestCase;

class GalleryServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/gallery_test_'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        // png/jpg/jpeg/bmp are gallery images; others must be ignored.
        foreach (['dragon.png', 'knight.jpg', 'orc.jpeg', 'goblin.bmp', 'notes.txt', 'archive.zip'] as $f) {
            file_put_contents($this->dir.'/'.$f, 'x');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testListReturnsOnlyImageExtensionsSortedByName(): void
    {
        $service = new GalleryService($this->dir);
        $names = array_column($service->list(), 'name');

        self::assertSame(['dragon.png', 'goblin.bmp', 'knight.jpg', 'orc.jpeg'], $names);
    }

    public function testListBuildsRenderableUrls(): void
    {
        $service = new GalleryService($this->dir);
        $byName = [];
        foreach ($service->list() as $item) {
            $byName[$item['name']] = $item['url'];
        }

        self::assertSame('/api/gallery/image?name=goblin.bmp', $byName['goblin.bmp']);
    }

    public function testResolvePathAcceptsValidImage(): void
    {
        $service = new GalleryService($this->dir);
        self::assertSame($this->dir.'/dragon.png', $service->resolvePath('dragon.png'));
    }

    public function testResolvePathRejectsTraversal(): void
    {
        $service = new GalleryService($this->dir);
        self::assertNull($service->resolvePath('../../etc/passwd'));
        self::assertNull($service->resolvePath('sub/dragon.png'));
    }

    public function testResolvePathRejectsDisallowedExtensionAndMissingFile(): void
    {
        $service = new GalleryService($this->dir);
        self::assertNull($service->resolvePath('notes.txt'));
        self::assertNull($service->resolvePath('archive.zip'));
        self::assertNull($service->resolvePath('ghost.png'));
        self::assertNull($service->resolvePath(''));
    }

    public function testMissingDirectoryYieldsEmptyList(): void
    {
        $service = new GalleryService($this->dir.'/does-not-exist');
        self::assertSame([], $service->list());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Profile;

use App\Modules\Profile\AvatarService;
use App\Modules\Profile\ProfileException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test, no DB — mirrors InvoicePdfGeneratorTest's approach of
 * exercising the real library (here: GD) against real bytes generated
 * in-test, rather than a binary fixture committed to the repo.
 */
final class AvatarServiceTest extends TestCase
{
    private string $storagePath;
    private AvatarService $avatars;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/invoicepro-avatars-' . uniqid();
        $this->avatars = new AvatarService($this->storagePath, 'http://localhost:8000/uploads/avatars');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            $this->removeDirectory($this->storagePath);
        }
    }

    public function testStoringAValidImageResizesToASquareAndReturnsARelativePath(): void
    {
        $file = $this->makeUploadedFile($this->pngBytes(400, 200), 'image/png');

        $path = $this->avatars->store($file, 1, 5, null);

        self::assertSame('1/5.png', $path);

        $fullPath = "{$this->storagePath}/{$path}";
        self::assertFileExists($fullPath);

        $dimensions = getimagesize($fullPath);
        self::assertSame(256, $dimensions[0]);
        self::assertSame(256, $dimensions[1]);
    }

    public function testUrlForBuildsAPublicUrlOrNull(): void
    {
        self::assertNull($this->avatars->urlFor(null));
        self::assertSame('http://localhost:8000/uploads/avatars/1/5.png', $this->avatars->urlFor('1/5.png'));
    }

    public function testReplacingWithADifferentFormatDeletesTheOldFile(): void
    {
        $oldPath = $this->avatars->store($this->makeUploadedFile($this->pngBytes(300, 300), 'image/png'), 1, 5, null);
        self::assertFileExists("{$this->storagePath}/{$oldPath}");

        $newPath = $this->avatars->store($this->makeUploadedFile($this->jpegBytes(300, 300), 'image/jpeg'), 1, 5, $oldPath);

        self::assertNotSame($oldPath, $newPath);
        self::assertFileDoesNotExist("{$this->storagePath}/{$oldPath}");
        self::assertFileExists("{$this->storagePath}/{$newPath}");
    }

    public function testDeleteRemovesTheFile(): void
    {
        $path = $this->avatars->store($this->makeUploadedFile($this->pngBytes(300, 300), 'image/png'), 1, 5, null);
        self::assertFileExists("{$this->storagePath}/{$path}");

        $this->avatars->delete($path);

        self::assertFileDoesNotExist("{$this->storagePath}/{$path}");
    }

    public function testAnOversizedFileIsRejected(): void
    {
        $file = new UploadedFile(
            (new Psr17Factory())->createStream('irrelevant, size is checked before the stream is read'),
            3 * 1024 * 1024,
            UPLOAD_ERR_OK,
            'big.png',
            'image/png'
        );

        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('Avatar must be 2MB or smaller.');

        $this->avatars->store($file, 1, 5, null);
    }

    public function testAnUnsupportedMimeTypeIsRejected(): void
    {
        $file = $this->makeUploadedFile('irrelevant', 'image/gif');

        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('Avatar must be a JPEG, PNG, or WebP image.');

        $this->avatars->store($file, 1, 5, null);
    }

    public function testCorruptImageBytesAreRejectedDespiteAnAllowedMimeType(): void
    {
        $file = $this->makeUploadedFile('this is not really a png', 'image/png');

        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage('Uploaded file is not a valid image.');

        $this->avatars->store($file, 1, 5, null);
    }

    private function makeUploadedFile(string $contents, string $mimeType): UploadedFile
    {
        return new UploadedFile(
            (new Psr17Factory())->createStream($contents),
            strlen($contents),
            UPLOAD_ERR_OK,
            'avatar',
            $mimeType
        );
    }

    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function jpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function removeDirectory(string $path): void
    {
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = "{$path}/{$item}";
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

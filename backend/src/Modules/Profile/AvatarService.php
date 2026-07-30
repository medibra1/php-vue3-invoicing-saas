<?php

declare(strict_types=1);

namespace App\Modules\Profile;

use GdImage;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Local-disk avatar storage — consistent with every other "clone and
 * run, no exotic external service" choice in this project (dompdf,
 * native PDO instead of an ORM). Files land under
 * {storagePath}/{tenant_id}/{user_id}.{ext}, served directly by the
 * webserver as a static file from public/uploads/avatars/.
 *
 * Every accepted upload is fully decoded and re-encoded through GD
 * rather than saved as-is: this — not the client-supplied MIME type
 * check alone, which is trivially spoofable — is what actually makes
 * the upload safe. Any embedded script/polyglot content in the
 * original bytes can't survive a real decode -> resize -> re-encode
 * round trip; only pixel data comes out the other side.
 */
final class AvatarService
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const DIMENSION = 256;

    /** @var array<string, string> Client-declared MIME type => stored file extension. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $storagePath,
        private readonly string $publicUrl
    ) {
    }

    /**
     * Validates, resizes to a fixed square, and stores the upload,
     * removing any existing avatar for this user once the new one is
     * safely written.
     *
     * @return string The relative path to save on the user record (not a full URL).
     * @throws ProfileException 422 on an invalid file, 500 if the file can't be written.
     */
    public function store(UploadedFileInterface $file, int $tenantId, int $userId, ?string $existingPath): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ProfileException('Avatar upload failed.');
        }

        if ($file->getSize() === null || $file->getSize() > self::MAX_BYTES) {
            throw new ProfileException('Avatar must be 2MB or smaller.');
        }

        $mimeType = $file->getClientMediaType();
        $extension = self::ALLOWED_TYPES[$mimeType] ?? null;

        if ($extension === null) {
            throw new ProfileException('Avatar must be a JPEG, PNG, or WebP image.');
        }

        $stream = $file->getStream();
        $stream->rewind();
        $source = @imagecreatefromstring($stream->getContents());

        if ($source === false) {
            throw new ProfileException('Uploaded file is not a valid image.');
        }

        $target = $this->resizeToSquare($source);
        imagedestroy($source);

        $directory = "{$this->storagePath}/{$tenantId}";

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            imagedestroy($target);

            throw new ProfileException('Could not store the avatar.', 500);
        }

        $relativePath = "{$tenantId}/{$userId}.{$extension}";
        $this->writeImage($target, "{$this->storagePath}/{$relativePath}", $mimeType);
        imagedestroy($target);

        // Only ever differs from $relativePath when the format changed
        // between uploads (e.g. png -> jpg) — same-format re-uploads
        // share a filename and were already overwritten by writeImage().
        if ($existingPath !== null && $existingPath !== $relativePath) {
            $this->delete($existingPath);
        }

        return $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null) {
            return;
        }

        $fullPath = "{$this->storagePath}/{$relativePath}";

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    public function urlFor(?string $relativePath): ?string
    {
        return $relativePath === null ? null : "{$this->publicUrl}/{$relativePath}";
    }

    private function resizeToSquare(GdImage $source): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $side = min($sourceWidth, $sourceHeight);
        $cropX = (int) (($sourceWidth - $side) / 2);
        $cropY = (int) (($sourceHeight - $side) / 2);

        $target = imagecreatetruecolor(self::DIMENSION, self::DIMENSION);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            self::DIMENSION,
            self::DIMENSION,
            $side,
            $side
        );

        return $target;
    }

    private function writeImage(GdImage $image, string $destination, string $mimeType): void
    {
        $written = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destination, 85),
            'image/png' => imagepng($image, $destination),
            'image/webp' => imagewebp($image, $destination, 85),
            default => false,
        };

        if (!$written) {
            throw new ProfileException('Could not save the avatar.', 500);
        }
    }
}

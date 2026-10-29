<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

/** Safe image uploads: size limit, real image check, re-encoded when GD is available, random or fixed names. */
final class Upload
{
    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /**
     * @param array|null $file   one entry of $_FILES
     * @param string $dir        absolute target directory
     * @param string $name       base name (fixed) or prefix (random suffix added unless $fixed)
     * @param int $maxKb
     * @param array $exts        allowed extensions
     * @param bool $fixed        overwrite "$name.ext" instead of a random file name
     * @return string            absolute path of the saved file
     */
    public static function image(?array $file, string $dir, string $name, int $maxKb, array $exts = ['jpg', 'png', 'webp'], bool $fixed = false): string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new HttpException('Choose an image to upload.', 422);
        if ($file['size'] > $maxKb * 1024) throw new HttpException("Image is too large (max {$maxKb} KB).", 422);
        if (!is_uploaded_file($file['tmp_name'])) throw new HttpException('Invalid upload.', 422);
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !isset(self::MIME[$info['mime']])) throw new HttpException('That file is not a supported image.', 422);
        $ext = self::MIME[$info['mime']];
        if (!in_array($ext, $exts, true)) throw new HttpException('Allowed formats: ' . strtoupper(implode(', ', $exts)) . '.', 422);
        if ($info[0] > 6000 || $info[1] > 6000) throw new HttpException('Image dimensions are too large.', 422);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $target = rtrim($dir, '/') . '/' . ($fixed ? $name : $name . '_' . bin2hex(random_bytes(8))) . '.' . $ext;

        // Re-encode to strip anything that is not pixels (metadata, appended payloads).
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
            if ($img) {
                if ($ext === 'png' || $ext === 'webp' || $ext === 'gif') { imagealphablending($img, false); imagesavealpha($img, true); }
                $ok = match ($ext) {
                    'jpg' => imagejpeg($img, $target, 88),
                    'png' => imagepng($img, $target, 6),
                    'webp' => function_exists('imagewebp') ? imagewebp($img, $target, 88) : false,
                    'gif' => imagegif($img, $target),
                };
                imagedestroy($img);
                if ($ok) return $target;
            }
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) throw new HttpException('Could not save the image.', 500);
        return $target;
    }
}

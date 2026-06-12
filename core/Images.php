<?php
/**
 * Images — automatska optimizacija uploadanih slika (GD):
 *  - glavna slika: smanjenje na max 1600 px + rekompresija (brže učitavanje)
 *  - thumbnail 480 px ("ime-thumb.ext") za kartice proizvoda/bloga
 * Ako GD nije dostupan ili format nije podržan, original ostaje netaknut.
 */

class Images
{
    public static function thumbName(string $filename): string
    {
        $dot = strrpos($filename, '.');
        return $dot === false ? $filename . '-thumb' : substr($filename, 0, $dot) . '-thumb' . substr($filename, $dot);
    }

    /** Vrati thumb ime ako thumb datoteka postoji, inače original. */
    public static function thumbOr(string $filename, string $dir): string
    {
        $t = self::thumbName($filename);
        return is_file(rtrim($dir, '/\\') . '/' . $t) ? $t : $filename;
    }

    /**
     * Optimiziraj sliku NA MJESTU + generiraj thumb. Tiho preskače na grešci.
     * @return bool true ako je optimizacija odrađena
     */
    public static function optimize(string $path, int $maxWidth = 1600, int $thumbWidth = 480, int $quality = 82): bool
    {
        if (!function_exists('imagecreatetruecolor') || !is_file($path)) return false;
        try {
            $info = @getimagesize($path);
            if (!$info) return false;
            [$w, $h, $type] = $info;
            if ($w < 1 || $h < 1) return false;

            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                IMAGETYPE_PNG  => @imagecreatefrompng($path),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                default        => null,
            };
            if (!$src) return false;

            $save = function ($img, string $to) use ($type, $quality): bool {
                return match ($type) {
                    IMAGETYPE_JPEG => imagejpeg($img, $to, $quality),
                    IMAGETYPE_PNG  => imagepng($img, $to, 8),
                    IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($img, $to, $quality) : false,
                    default        => false,
                };
            };
            $scale = function ($from, int $fw, int $fh, int $toW) use ($type) {
                $toH = (int) round($fh * $toW / $fw);
                $dst = imagecreatetruecolor($toW, $toH);
                if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                }
                imagecopyresampled($dst, $from, 0, 0, 0, 0, $toW, $toH, $fw, $fh);
                return $dst;
            };

            // Glavna: smanji samo ako je veća od maxWidth (uvijek rekomprimiraj radi težine)
            if ($w > $maxWidth) {
                $main = $scale($src, $w, $h, $maxWidth);
                $save($main, $path);
                imagedestroy($main);
            } else {
                $save($src, $path);
            }

            // Thumb za kartice
            if ($w > $thumbWidth) {
                $thumb = $scale($src, $w, $h, $thumbWidth);
                $save($thumb, self::thumbPath($path));
                imagedestroy($thumb);
            }

            imagedestroy($src);
            return true;
        } catch (Throwable $e) {
            error_log('[Images] ' . $e->getMessage());
            return false;
        }
    }

    private static function thumbPath(string $path): string
    {
        $dir = dirname($path);
        return $dir . '/' . self::thumbName(basename($path));
    }
}

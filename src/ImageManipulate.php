<?php
namespace Fluxion;

class ImageManipulate
{

    public static function createThumbFromFile($file, int $max_size = 250): ?string
    {

        if (preg_match('/\.[A-Z\d]+$/i', $file)) {

            $file_name = preg_replace('/\.[A-Z\d]+$/i', '', $file) . '_thumb.jpg';

            $scr = imagecreatefromstring(file_get_contents($file));

            $nowX = imagesx($scr);
            $nowY = imagesy($scr);

            if ($nowX > $nowY) {

                $newX = $max_size;
                $newY = round($max_size * $nowY / $nowX);

            } else {

                $newX = round($max_size * $nowX / $nowY);
                $newY = $max_size;

            }

            self::createThumb($scr, $file_name, $newX, $newY, 90);

            imagedestroy($scr);

            return $file_name;

        } else {

            return null;

        }

    }

    public static function createThumbFromJpeg($file, $file_name, int $max_size = 300): ?string
    {

        ini_set('memory_limit', '1024M');

        if (preg_match('/\.[A-Z\d]+$/i', $file)) {

            $file_name = $file_name
                ?? preg_replace('/\.[A-Z\d]+$/i', '', $file) . '_thumb.jpg';

            $size = filesize($file);

            if ($size > 50000000) {
                return null;
            }

            $scr = imagecreatefromjpeg($file);

            self::createThumb($scr, $file_name, $max_size, $max_size, 90);

            imagedestroy($scr);

            return $file_name;

        } else {

            return null;

        }

    }

    public static function createThumbFromString($str, $file, $newX = 200, $newY = 200)
    {

        if ($src = @imagecreatefromstring($str)) {

            self::createThumb($src, $file, $newX, $newY);

            imagedestroy($src);

        }

    }

    public static function createThumb($scr, $file, $newX = 200, $newY = 200, $quality = 100)
    {

        $oldX = imagesx($scr);
        $oldY = imagesy($scr);

        $calX = $oldX / $newX;
        $calY = $oldY / $newY;

        $min = min($calX, $calY);

        $xt = $min * $newX;
        $x1 = ($oldX - $xt) * 0.5;
        $x2 = $oldX - $x1;

        $yt = $min * $newY;
        $y1 = ($oldY - $yt) * 0.5;
        $y2 = $oldY - $y1;

        $x1 = (int) $x1;
        $x2 = (int) $x2;
        $y1 = (int) $y1;
        $y2 = (int) $y2;

        $y1_ = floor($y1 * 0.5);

        $img = imagecreatetruecolor($newX, $newY);

        imagecopyresampled($img, $scr, 0, 0, $x1, $y1_, $newX, $newY, $x2-$x1, $y2-$y1);

        if (file_exists($file)) {

            unlink($file);

        }

        $dir = dirname($file);

        Upload::createDir($dir);

        @imagejpeg($img, $file, $quality);

        imagedestroy($img);

    }

}

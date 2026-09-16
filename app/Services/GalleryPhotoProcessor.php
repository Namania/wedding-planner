<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class GalleryPhotoProcessor
{
    /**
     * @return array{path: string, thumb_path: string, width: int, height: int, size_bytes: int}
     */
    public function process(UploadedFile $file): array
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $manager = new ImageManager(GdDriver::class);
            $encoder = new JpegEncoder(quality: (int) config('gallery.jpeg_quality'), strip: true);

            $maxFull = (int) config('gallery.max_edge_full');
            $maxThumb = (int) config('gallery.max_edge_thumb');

            $image = $manager->decodePath($file->getRealPath());
            $image->scaleDown(width: $maxFull, height: $maxFull);

            $width = $image->width();
            $height = $image->height();
            $full = (string) $image->encode($encoder);

            $image->scaleDown(width: $maxThumb, height: $maxThumb);
            $thumb = (string) $image->encode($encoder);

            $disk = Storage::disk(config('gallery.disk'));
            $directory = 'gallery/'.now()->format('Y/m');
            $name = Str::uuid()->toString();

            $path = "{$directory}/{$name}.jpg";
            $thumbPath = "{$directory}/{$name}_thumb.jpg";

            $disk->put($path, $full);
            $disk->put($thumbPath, $thumb);

            return [
                'path' => $path,
                'thumb_path' => $thumbPath,
                'width' => $width,
                'height' => $height,
                'size_bytes' => strlen($full),
            ];
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }
}

<?php

namespace App\Services;

use App\Models\OrderDeliveryPhoto;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;
use RuntimeException;

class DeliveryPhotoVariantGenerator
{
    public const CACHE_CONTROL = 'private, max-age=31536000, immutable';

    public function generate(OrderDeliveryPhoto $photo, bool $force = false): OrderDeliveryPhoto
    {
        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick is required to generate delivery photo variants.');
        }

        if (! $force && filled($photo->thumbnail_path) && filled($photo->display_path)) {
            return $photo;
        }

        $disk = Storage::disk($photo->disk ?: 'r2');
        $contents = $disk->get($photo->path);

        if ($contents === null || $contents === '') {
            throw new RuntimeException("Delivery photo [{$photo->path}] could not be read.");
        }

        $source = null;
        $thumbnail = null;
        $display = null;

        try {
            $source = new Imagick;
            $source->readImageBlob($contents);
            $source->setIteratorIndex(0);
            if (method_exists($source, 'autoOrientImage')) {
                $source->autoOrientImage();
            } elseif (method_exists($source, 'autoOrient')) {
                $source->autoOrient();
            }
            $source->setImageColorspace(Imagick::COLORSPACE_SRGB);

            $thumbnail = clone $source;
            $thumbnail->cropThumbnailImage(360, 360);

            $display = clone $source;
            $display->thumbnailImage(1920, 1920, true);

            $thumbnailPath = $this->variantPath($photo->path, 'thumb');
            $displayPath = $this->variantPath($photo->path, 'display');

            $this->storeJpeg($disk, $thumbnailPath, $thumbnail, 78);
            $this->storeJpeg($disk, $displayPath, $display, 84);

            $photo->forceFill([
                'thumbnail_path' => $thumbnailPath,
                'display_path' => $displayPath,
            ])->save();

            return $photo->refresh();
        } catch (ImagickException $exception) {
            throw new RuntimeException(
                "Delivery photo [{$photo->path}] is not in an image format supported by this server.",
                previous: $exception,
            );
        } finally {
            $thumbnail?->clear();
            $display?->clear();
            $source?->clear();
        }
    }

    public function storageOptions(string $contentType = 'image/jpeg'): array
    {
        return [
            'visibility' => 'private',
            'ContentType' => $contentType,
            'CacheControl' => self::CACHE_CONTROL,
        ];
    }

    protected function variantPath(string $originalPath, string $variant): string
    {
        $directory = pathinfo($originalPath, PATHINFO_DIRNAME);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $prefix = $directory === '.' ? '' : $directory.'/';

        return "{$prefix}{$filename}.{$variant}.jpg";
    }

    protected function storeJpeg(
        FilesystemAdapter $disk,
        string $path,
        Imagick $image,
        int $quality,
    ): void {
        $image->setImageFormat('jpeg');
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality($quality);
        $image->setInterlaceScheme(Imagick::INTERLACE_JPEG);
        $image->stripImage();

        if (! $disk->put($path, $image->getImagesBlob(), $this->storageOptions())) {
            throw new RuntimeException("Delivery photo variant [{$path}] could not be stored.");
        }
    }
}

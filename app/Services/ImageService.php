<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    private ImageManager $manager;
    private string $disk;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->disk = config('filesystems.default', 's3');
    }

    /**
     * Upload and resize hero image
     */
    public function uploadHeroImage(UploadedFile $file, string $eventSlug): string
    {
        $image = $this->manager->read($file->getContent());
        $image->scaleDown(width: 1920, height: 1080);

        $filename = sprintf(
            'events/%s/hero-%s.jpg',
            $eventSlug,
            Str::random(8)
        );

        Storage::disk($this->disk)->put(
            $filename,
            $image->toJpeg(quality: 85),
            'public'
        );

        return $filename;
    }

    /**
     * Delete image from storage
     */
    public function deleteImage(string $path): bool
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    /**
     * Get full URL for image
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }
}

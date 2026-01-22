<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;

class File extends Model
{
    use HasFactory, WhoDidIt;

    public const SIZES = [
        'thumbnail' => [
            'width'  => 250,
            'height' => 250,
        ],
        'medium' => [
            'width'  => 1440,
            'height' => 1440,
        ],
        'large' => [
            'width'  => 2500,
            'height' => 2500,
        ],
    ];

    public $casts = [
        'responsive_images' => 'array',
        'responsive_paths'  => 'json',
    ];

    protected $appends = [
        'size_formatted',
    ];

    protected $fillable = [
        'name',
        'path',
        'type',
        'size',
        'disk',
        'responsive_paths',
    ];

    public static function upload(UploadedFile|string $file, $path = 'uploads', $disk = null, $watermark = false): self
    {
        if (is_string($file)) {
            if (str_contains($file, 'http')) {
                $file = UploadedFile::fake()->createWithContent(basename($file), file_get_contents($file));
            } else {
                $file = new UploadedFile($file, basename($file));
            }
        }
        $isImage = str_contains($file->getMimeType(), 'image');

        // Modify uploaded file with watermark
        if ($watermark) {
            $watermark      = public_path('images/watermark.png');
            $tmp            = tempnam(sys_get_temp_dir(), 'watermark').'.'.$file->getClientOriginalExtension();
            $watermarkImage = new Imagine;
            $image          = new Imagine;
            $image          = $image->open($file->getRealPath());
            $watermark      = $watermarkImage->open($watermark);
            $imageSize      = $image->getSize();

            // Calculate the number of watermarks needed in each dimension
            $numWatermarksHeight = ceil($imageSize->getHeight() / 300);
            $numWatermarksWidth  = ceil($imageSize->getWidth() / 500);

            // Loop over the image and paste the watermark at the correct positions
            for ($i = 0; $i < $numWatermarksHeight; $i++) {
                for ($j = 0; $j < $numWatermarksWidth; $j++) {
                    $x = $j * 500;
                    $y = $i * 300;
                    $image->paste($watermark, new Point($x, $y));
                }
            }

            $image->save($tmp);
            $file = new UploadedFile($tmp, $file->getClientOriginalName());
        }

        $disk         = $disk ?: config('filesystems.default');
        $uniqueNumber = time();
        $ext          = mb_strtolower($file->getClientOriginalExtension());
        $mime         = $file->getMimeType();
        $fileName     = mb_strtolower(str_replace(".$ext", '', $file->getClientOriginalName())."_$uniqueNumber.$ext");
        $directory    = mb_rtrim(mb_ltrim($path, '/'), '/').'/'.str_replace(".$ext", '', $fileName);
        $path         = $file->storeAs($directory, $fileName, $disk);

        $media = new self([
            'name'             => $file->getClientOriginalName(),
            'path'             => $path,
            'type'             => $mime,
            'size'             => $file->getSize() / 1000,
            'disk'             => $disk,
            'responsive_paths' => [
                'original' => $path,
            ],
        ]);

        if ($isImage) {
            foreach (self::SIZES as $name => $dims) {
                $tmp   = tempnam(sys_get_temp_dir(), $name).'.'.$file->getClientOriginalExtension();
                $image = new Imagine;
                $image = $image->open($file->getRealPath());

                if ($file->getClientOriginalExtension() !== 'svg') {
                    $image = $image->thumbnail(new Box($dims['width'], $dims['height']));
                }
                $image->save($tmp);
                $thumbnail               = new UploadedFile($tmp, $file->getClientOriginalName());
                $media->responsive_paths = array_merge($media->responsive_paths, [
                    $name => $thumbnail->storeAs($directory, str_replace(".$ext", '', $fileName)."_$name.$ext", $disk),
                ]);
                unlink($tmp);
            }
        }

        $media->save();

        return $media;
    }

    // boot on delete delete file from storage
    protected static function booted(): void
    {
        parent::booted();
        self::deleting(function ($file) {
            // remove all files
            $filePath = str_replace('/storage/', '', $file->path);
            Storage::disk($file->disk)->delete($filePath);

            // remove all responsive images
            foreach ($file->responsive_paths as $responsiveImage) {
                $filePath = str_replace('/storage/', '', $responsiveImage);
                Storage::disk($file->disk)->delete($filePath);
            }

        });
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn ($value): string => Storage::disk($this->disk)->url(mb_ltrim($value, '/')));
    }

    protected function sizeFormatted(): Attribute
    {
        return Attribute::get(fn (): string => Number::fileSize($this->size, 1, 2));
    }
}

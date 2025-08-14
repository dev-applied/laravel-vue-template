<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

/**
 * App\Models\File
 *
 * @property int $id
 * @property string $name
 * @property string $path
 * @property string $type
 * @property int $size
 * @property string|null $disk
 * @property array<array-key, mixed>|null $responsive_paths
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property int|null $deleted_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $created_by
 * @property-read User|null $deleted_by
 * @property-read TFactory|null $use_factory
 * @property-read User|null $updated_by
 * @property-read mixed $url
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereCreatedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereDeletedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereResponsivePaths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereUpdatedById($value)
 *
 * @mixin \Eloquent
 *
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
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
                ImageOptimizer::optimize($tmp);
                $thumbnail               = new UploadedFile($tmp, $file->getClientOriginalName());
                $media->responsive_paths = array_merge($media->responsive_paths, [$name => $thumbnail->storeAs($directory, str_replace(".$ext", '', $fileName)."_$name.$ext", $disk)]);
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
        return Attribute::get(function ($value): string {
            $units     = ['kb', 'mb', 'gb', 'tb'];
            $units[-1] = 'b';

            $bytes = max($this->size, 0);
            $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow   = min($pow, count($units) - 1);

            $bytes /= pow(1024, $pow);

            return round($bytes, 2).' '.$units[$pow];
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Files\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;
use Modules\Files\Database\Factories\FileFactory;

class File extends Model
{
    use HasFactory, WhoDidIt;

    public const SIZES = [
        'thumbnail' => ['width' => 250,  'height' => 250],
        'medium'    => ['width' => 1440, 'height' => 1440],
        'large'     => ['width' => 2500, 'height' => 2500],
    ];

    public $casts = [
        'responsive_paths' => 'array',
        'processed'        => 'boolean',
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
        'folder_id',
        'responsive_paths',
        'processed',
    ];

    /**
     * Upload a file to the configured disk and generate its image variants
     * in-request. This is the `storage=local` path; the presigned S3 path
     * creates the row first and calls processVariants() once the object lands.
     */
    public static function upload(UploadedFile|string $file, string $path = 'uploads', ?string $disk = null, bool $watermark = false, int|string|null $folderId = null): self
    {
        $file = self::normalizeToUploadedFile($file);

        if ($watermark) {
            $file = self::applyWatermark($file);
        }

        $disk      = $disk ?: config('filesystems.default');
        $ext       = mb_strtolower($file->getClientOriginalExtension());
        $fileName  = self::uniqueFileName($file->getClientOriginalName(), $ext);
        $directory = self::directoryFor($path, $fileName, $ext);
        $storedAt  = $file->storeAs($directory, $fileName, $disk);

        $media = new self([
            'name'             => $file->getClientOriginalName(),
            'path'             => $storedAt,
            'type'             => $file->getMimeType(),
            'size'             => (int) ($file->getSize() / 1000),
            'disk'             => $disk,
            'folder_id'        => $folderId,
            'responsive_paths' => ['original' => $storedAt],
            'processed'        => true,
        ]);
        $media->save();

        $media->processVariants($file->getRealPath());

        return $media;
    }

    /**
     * Generate the image variants for this record. Safe to call more than once
     * and a no-op for non-images, so the S3 event path can retry.
     *
     * @param  string|null  $sourcePath  local path to read from; defaults to pulling
     *                                   the stored original off the disk.
     */
    public function processVariants(?string $sourcePath = null): self
    {
        if (! str_contains((string) $this->type, 'image')) {
            $this->forceFill(['processed' => true])->save();

            return $this;
        }

        $disk      = Storage::disk($this->disk);
        $cleanup   = false;
        $original  = $this->responsive_paths['original'] ?? $this->path;
        $extension = mb_strtolower(pathinfo((string) $this->name, PATHINFO_EXTENSION));

        if ($sourcePath === null) {
            // Pull the stored object down so Imagine has something local to read.
            $sourcePath = tempnam(sys_get_temp_dir(), 'file-src').'.'.$extension;
            file_put_contents($sourcePath, $disk->get($original));
            $cleanup = true;
        }

        $paths     = $this->responsive_paths ?? [];
        $directory = dirname($original);
        $baseName  = pathinfo($original, PATHINFO_FILENAME);

        // SVGs have no meaningful raster variants — store the original under
        // every size key so callers asking for `thumbnail` still resolve.
        if ($extension === 'svg') {
            foreach (array_keys(self::SIZES) as $name) {
                $paths[$name] = $original;
            }
        } else {
            foreach (self::SIZES as $name => $dims) {
                $tmp   = tempnam(sys_get_temp_dir(), $name).'.'.$extension;
                $image = (new Imagine)->open($sourcePath);
                $image->thumbnail(new Box($dims['width'], $dims['height']))->save($tmp);

                $variantPath = $directory.'/'.$baseName.'_'.$name.'.'.$extension;
                $disk->put($variantPath, file_get_contents($tmp));
                $paths[$name] = $variantPath;

                unlink($tmp);
            }
        }

        if ($cleanup) {
            unlink($sourcePath);
        }

        $this->forceFill([
            'responsive_paths' => $paths,
            'processed'        => true,
        ])->save();

        return $this;
    }

    /** Resolve a stored path for the requested size, falling back to the original. */
    public function pathForSize(string $size = 'original'): string
    {
        return $this->responsive_paths[$size] ?? $this->responsive_paths['original'] ?? $this->path;
    }

    protected static function newFactory(): Factory
    {
        return FileFactory::new();
    }

    protected static function booted(): void
    {
        parent::booted();

        self::deleting(function (self $file) {
            $disk = Storage::disk($file->disk);

            foreach (array_unique(array_merge([$file->path], array_values($file->responsive_paths ?? []))) as $path) {
                $disk->delete(mb_ltrim(str_replace('/storage/', '', (string) $path), '/'));
            }
        });
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn ($value): string => Storage::disk($this->disk)->url(mb_ltrim((string) $value, '/')));
    }

    protected function sizeFormatted(): Attribute
    {
        return Attribute::get(fn (): string => Number::fileSize($this->size, 1, 2));
    }

    private static function normalizeToUploadedFile(UploadedFile|string $file): UploadedFile
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        return str_contains($file, 'http')
            ? UploadedFile::fake()->createWithContent(basename($file), (string) file_get_contents($file))
            : new UploadedFile($file, basename($file));
    }

    private static function applyWatermark(UploadedFile $file): UploadedFile
    {
        $watermarkPath = public_path('images/watermark.png');

        if (! file_exists($watermarkPath)) {
            return $file;
        }

        $tmp       = tempnam(sys_get_temp_dir(), 'watermark').'.'.$file->getClientOriginalExtension();
        $image     = (new Imagine)->open($file->getRealPath());
        $watermark = (new Imagine)->open($watermarkPath);
        $size      = $image->getSize();

        for ($i = 0; $i < ceil($size->getHeight() / 300); $i++) {
            for ($j = 0; $j < ceil($size->getWidth() / 500); $j++) {
                $image->paste($watermark, new Point($j * 500, $i * 300));
            }
        }

        $image->save($tmp);

        return new UploadedFile($tmp, $file->getClientOriginalName());
    }

    /**
     * A name that is actually unique, which `time()` was not.
     *
     * `time()` is one-second granular, and `directoryFor()` derives the
     * DIRECTORY from this same string — so two files sharing an original name
     * inside one second produced an identical path. `storeAs` overwrites
     * without complaint and there is no unique index on `files.path`, so the
     * result was N rows and one object: several people's uploads resolving to
     * the last-written bytes, and deleting any one row unlinking the file for
     * all of them.
     *
     * Not a rare interleaving either — it is a phone posting four photos all
     * called `image.jpg`, which the module's own dropzone does in one request.
     */
    private static function uniqueFileName(string $originalName, string $ext): string
    {
        $base = str_replace(".$ext", '', $originalName);

        return mb_strtolower($base.'_'.time().'_'.Str::random(8).".$ext");
    }

    private static function directoryFor(string $path, string $fileName, string $ext): string
    {
        return mb_rtrim(mb_ltrim($path, '/'), '/').'/'.str_replace(".$ext", '', $fileName);
    }
}

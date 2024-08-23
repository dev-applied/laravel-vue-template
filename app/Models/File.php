<?php

namespace App\Models;

use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\File
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $type
 * @property int|null $size
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $client
 * @property-read int|null $client_count
 * @property-read \App\Models\User|null $created_by
 * @property-read \App\Models\User|null $deleted_by
 * @property-read \App\Models\FileReport|null $report
 * @property-read array $responsive_images
 * @property-read \App\Models\User|null $updated_by
 * @method static \Illuminate\Database\Eloquent\Builder|File newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|File newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|File query()
 * @method static \Illuminate\Database\Eloquent\Builder|File whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereCreatedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereUpdatedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder|File whereUrl($value)
 * @mixin \Eloquent
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class File extends Model
{
    use HasFactory, WhoDidIt;

    public const SIZES = [
        'thumbnail' => [
            'width' => 150,
            'height' => 150,
        ],
        'medium_large' => [
            'width' => 768,
            'height' => 768,
        ],
    ];

    protected $fillable = [
        'name',
        'url',
        'type',
        'size',
    ];

    //boot on delete delete file from storage
    protected static function booted()
    {
        parent::booted();
        self::deleting(function ($file) {
            //remove all files
            $filePath = str_replace("/storage/", "", $file->url);
            Storage::disk('public')->delete($filePath);

            //remove all responsive images
            foreach ($file->responsive_images as $responsiveImage) {
                $filePath = str_replace("/storage/", "", $responsiveImage);
                Storage::disk('public')->delete($filePath);
            }

        });
    }

    public function responsiveImages(): Attribute
    {
        return Attribute::get(function (): array {
            $responsive = ['original' => $this->url];
            if (str_contains($this->type, 'image')) {
                $extension = pathinfo($this->url, PATHINFO_EXTENSION);
                foreach (File::SIZES as $name => $dimensions) {
                    $responsive[$name] = str_replace(".$extension", '', $this->url) . '_' . $name . '.' . $extension;
                }
            }

            return $responsive;
        });
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn($value) => Storage::url(ltrim($value, '/')));
    }
}

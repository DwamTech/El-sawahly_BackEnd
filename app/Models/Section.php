<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    // Content type constants
    const TYPE_ARTICLE = 'مقال';
    const TYPE_BOOK    = 'كتب';
    const TYPE_VIDEO   = 'فيديو';
    const TYPE_AUDIO   = 'صوت';

    protected $fillable = [
        'name',
        'name_sw',
        'type',
        'slug',
        'description',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function audios()
    {
        return $this->hasMany(Audio::class);
    }

    public function visuals()
    {
        return $this->hasMany(Visual::class);
    }

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::queryByReference(static::query(), $value, $field)->firstOrFail();
    }

    public static function resolveReference(mixed $reference, bool $activeOnly = false): ?self
    {
        $query = static::queryByReference(static::query(), $reference);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->first();
    }

    public static function queryByReference(Builder $query, mixed $reference, ?string $field = null): Builder
    {
        if ($field) {
            return $query->where($field, $reference);
        }

        if (is_numeric($reference)) {
            return $query->whereKey($reference);
        }

        $value = trim((string) $reference);

        return $query->where(function (Builder $builder) use ($value) {
            $builder
                ->where('slug', $value)
                ->orWhere('name_sw', strtoupper($value))
                ->orWhere('name', $value);
        });
    }
}

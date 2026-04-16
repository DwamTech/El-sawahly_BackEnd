<?php

namespace App\Models;

use App\Traits\HasDefaultSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audio extends Model
{
    use HasDefaultSection, HasFactory;

    protected $table = 'audios';

    protected $fillable = [
        'section_id',
        'user_id',
        'title',
        'description',
        'type',
        'file_path',
        'url',
        'thumbnail',
        'keywords',
        'views_count',
        'rating',
    ];

    protected $casts = [
        'rating' => 'float',
        'views_count' => 'integer',
        'section_id' => 'integer',
        'user_id' => 'integer',
    ];

    protected $appends = [
        'embed_url',
    ];

    public function getThumbnailAttribute($value)
    {
        if ($value) {
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            return asset('storage/'.$value);
        }

        $url = (string) ($this->attributes['url'] ?? '');
        $youtubeId = $this->extractYoutubeId($url);
        if ($youtubeId) {
            return "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg";
        }

        return null;
    }

    public function getFilePathAttribute($value)
    {
        if ($value) {
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            return asset('storage/'.$value);
        }

        return null;
    }

    public function getEmbedUrlAttribute()
    {
        $type = $this->attributes['type'] ?? null;

        if ($type === 'upload') {
            return $this->file_path;
        }

        $url = (string) ($this->attributes['url'] ?? '');
        if ($url === '') {
            return null;
        }

        $youtubeId = $this->extractYoutubeId($url);
        if ($youtubeId) {
            $params = http_build_query([
                'rel' => 0,
                'modestbranding' => 1,
                'playsinline' => 1,
            ]);

            return "https://www.youtube-nocookie.com/embed/{$youtubeId}?{$params}";
        }

        return $url;
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    private function extractYoutubeId(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $host = strtolower($host);

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');

            return $id !== '' ? $id : null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str($query, $params);
            if (! empty($params['v']) && is_string($params['v'])) {
                return $params['v'];
            }

            if (preg_match('~^/embed/([^/?]+)~', $path, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}

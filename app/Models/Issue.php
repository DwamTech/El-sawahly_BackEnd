<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'issue_number',
        'cover_image',
        'cover_image_alt',
        'pdf_file',
        'hijri_date',
        'gregorian_date',
        'views_count',
        'status',
        'published_at',
        'is_featured',
        'sort_order',
        'user_id',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'issue_number' => 'integer',
    ];

    public function getCoverImageAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        return null;
    }

    public function getCoverImageAltAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        return null;
    }

    public function getPdfFileAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

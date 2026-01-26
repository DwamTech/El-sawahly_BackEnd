<?php

namespace App\Models;

use App\Traits\HasDefaultSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasDefaultSection, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'section_id',
        // 'issue_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'author_name',
        'featured_image',
        'gregorian_date',
        'hijri_date',
        'references',
        'keywords',
        'status',
        'published_at',
        'views_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'section_id' => 'integer',
    ];

    public function getFeaturedImageAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        return null;
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

}

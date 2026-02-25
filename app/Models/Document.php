<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'source_type',
        'file_path',
        'source_link',
        'cover_type',
        'cover_path',
        'keywords',
        'file_type',
        'file_size',
        'views_count',
        'downloads_count',
        'user_id',
        'section_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'views_count' => 'integer',
        'downloads_count' => 'integer',
        'file_size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}

<?php

namespace App\Models;

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
}

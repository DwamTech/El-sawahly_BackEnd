<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
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
        'views_count',
        'rating_sum',
        'rating_count',
        'author_name',
        'type',
        'book_series_id',
        'section_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'views_count' => 'integer',
        'rating_sum' => 'decimal:2',
        'rating_count' => 'integer',
    ];

    protected $appends = ['average_rating'];

    public function series()
    {
        return $this->belongsTo(BookSeries::class, 'book_series_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function getAverageRatingAttribute()
    {
        if ($this->rating_count == 0) {
            return 0;
        }

        return round($this->rating_sum / $this->rating_count, 1);
    }

    // Get suggestions (siblings in the same series)
    public function scopeRelated($query, $bookId)
    {
        $book = $this->find($bookId);
        if (! $book || ! $book->book_series_id) {
            return $query->where('id', 0);
        } // Empty result

        return $query->where('book_series_id', $book->book_series_id)
            ->where('id', '!=', $bookId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileChunk extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'chunk_index',
        'chunk_path',
        'chunk_size',
        'uploaded_at',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'chunk_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}

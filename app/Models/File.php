<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_UPLOADING = 'uploading';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'file_name',
        'original_name',
        'file_type',
        'mime_type',
        'file_size',
        'file_path',
        'file_url',
        'status',
        'upload_session_id',
        'total_chunks',
        'uploaded_chunks',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_chunks' => 'integer',
        'uploaded_chunks' => 'integer',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chunks()
    {
        return $this->hasMany(FileChunk::class);
    }

    public function session()
    {
        return $this->belongsTo(UploadSession::class, 'upload_session_id', 'session_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'total_files',
        'completed_files',
        'failed_files',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'total_files' => 'integer',
        'completed_files' => 'integer',
        'failed_files' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

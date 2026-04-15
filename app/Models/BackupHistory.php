<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BackupHistory extends Model
{
    protected $fillable = [
        'type',
        'status',
        'file_name',
        'file_size',
        'message',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

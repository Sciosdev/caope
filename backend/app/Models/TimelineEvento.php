<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEvento extends Model
{
    use HasFactory;

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}

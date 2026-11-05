<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpedienteFileDeletion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_MISSING = 'missing';

    public const STATUS_RETAINED_SHARED = 'retained_shared';

    public const STATUS_RETAINED_UNSAFE = 'retained_unsafe';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];
}

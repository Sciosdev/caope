<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentRun extends Model
{
    protected $fillable = [
        'request_id',
        'requested_by',
        'provider',
        'ref',
        'status',
        'conclusion',
        'workflow_run_id',
        'workflow_url',
        'commit_sha',
        'error_message',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['requested', 'queued', 'in_progress', 'waiting'], true);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'completed') {
            return match ($this->conclusion) {
                'success' => 'Completado',
                'cancelled' => 'Cancelado',
                'skipped' => 'Omitido',
                default => 'Falló',
            };
        }

        return match ($this->status) {
            'requested' => 'Solicitado',
            'queued' => 'En cola',
            'in_progress' => 'En ejecución',
            'waiting' => 'Esperando aprobación',
            'failed_to_dispatch' => 'No se pudo iniciar',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function badgeClass(): string
    {
        if ($this->status === 'completed') {
            return $this->conclusion === 'success' ? 'text-bg-success' : 'text-bg-danger';
        }

        return match ($this->status) {
            'requested', 'queued', 'waiting' => 'text-bg-warning',
            'in_progress' => 'text-bg-primary',
            'failed_to_dispatch' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentRun extends Model
{
    public const ACTIVE_STATUSES = ['requested', 'queued', 'pending', 'in_progress', 'waiting'];

    public const BLOCKING_AGENT_STATUSES = ['running', 'interrupted', 'recovery_required'];

    public const AGENT_STAGES = [
        'preflight' => 'Comprobando requisitos',
        'backup' => 'Creando respaldo',
        'maintenance' => 'Activando mantenimiento',
        'checkout' => 'Actualizando código',
        'dependencies' => 'Instalando dependencias',
        'migrations' => 'Actualizando base de datos',
        'cache' => 'Preparando cachés',
        'verify' => 'Verificando la versión',
        'rollback' => 'Restaurando la versión anterior',
        'completed' => 'Instalación terminada',
    ];

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
        'agent_status',
        'agent_stage',
        'agent_started_at',
        'agent_finished_at',
        'agent_heartbeat_at',
        'agent_result_key',
        'agent_result_at',
    ];

    protected function casts(): array
    {
        return [
            'workflow_run_id' => 'integer',
            'agent_started_at' => 'datetime',
            'agent_finished_at' => 'datetime',
            'agent_heartbeat_at' => 'datetime',
            'agent_result_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            || in_array($this->agent_status, self::BLOCKING_AGENT_STATUSES, true);
    }

    public function scopeBlocking(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereIn('status', self::ACTIVE_STATUSES)
                ->orWhereIn('agent_status', self::BLOCKING_AGENT_STATUSES);
        });
    }

    public function stageLabel(): string
    {
        return self::AGENT_STAGES[$this->agent_stage] ?? match ($this->status) {
            'requested', 'queued', 'pending' => 'Validando en GitHub',
            'waiting' => 'Esperando aprobación',
            'completed' => 'Validación de GitHub terminada',
            default => 'Esperando al agente',
        };
    }

    public function statusLabel(): string
    {
        $agentLabel = match ($this->agent_status) {
            'interrupted' => 'Recuperación pendiente',
            'recovery_required' => 'Recuperación incompleta',
            'failed' => 'Falló en el servidor',
            'timed_out' => 'Tiempo agotado',
            'running' => 'Instalando en el servidor',
            default => null,
        };

        if ($agentLabel !== null) {
            return $agentLabel;
        }

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
            'pending' => 'En cola',
            'in_progress' => 'En ejecución',
            'waiting' => 'Esperando aprobación',
            'failed_to_dispatch' => 'No se pudo iniciar',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function badgeClass(): string
    {
        if (in_array($this->agent_status, ['failed', 'timed_out', 'interrupted', 'recovery_required'], true)) {
            return 'text-bg-danger';
        }

        if ($this->agent_status === 'running') {
            return 'text-bg-primary';
        }

        if ($this->status === 'completed') {
            return $this->conclusion === 'success' ? 'text-bg-success' : 'text-bg-danger';
        }

        return match ($this->status) {
            'requested', 'queued', 'pending', 'waiting' => 'text-bg-warning',
            'in_progress' => 'text-bg-primary',
            'failed_to_dispatch' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}

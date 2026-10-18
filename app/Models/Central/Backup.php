<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dump of a shop's database (or of the central registry, with no tenant).
 */
class Backup extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TRIGGER_SCHEDULED = 'scheduled';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_PRE_RESTORE = 'pre_restore';
    public const TRIGGER_PRE_MIGRATE = 'pre_migrate';
    public const TRIGGER_PRE_DELETE = 'pre_delete';

    public const OFFSITE_DISABLED = 'disabled';
    public const OFFSITE_UPLOADED = 'uploaded';
    public const OFFSITE_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'database', 'label', 'trigger', 'status', 'path', 'size_bytes', 'checksum',
        'offsite_status', 'offsite_error', 'error', 'created_by', 'started_at', 'finished_at', 'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.central_connection');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** By name, not tenant_id: a deleted shop's last backups also lose their tenant. */
    public function isCentral(): bool
    {
        return $this->database === config('database.connections.'.config('tenancy.central_connection').'.database');
    }

    public function isRestorable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && ! $this->isCentral() && $this->tenant !== null;
    }
}

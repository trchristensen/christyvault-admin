<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAcknowledgement extends Model
{
    use HasFactory;

    public const METHOD_AUTHENTICATED = 'authenticated';

    public const METHOD_PAPER_IMPORT = 'paper_import';

    protected $fillable = [
        'standard_operating_procedure_revision_id',
        'employee_id',
        'user_id',
        'method',
        'signed_name',
        'acknowledgement_text',
        'locale',
        'evidence_hash',
        'evidence_file_path',
        'ip_address',
        'user_agent',
        'recorded_by_user_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(StandardOperatingProcedureRevision::class, 'standard_operating_procedure_revision_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}

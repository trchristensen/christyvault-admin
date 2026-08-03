<?php

namespace App\Models;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MaintenanceAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'location_id', 'asset_tag', 'qr_token', 'name', 'category', 'status',
        'criticality', 'description', 'manufacturer', 'model', 'serial_number', 'license_plate', 'year',
        'acquired_on', 'warranty_expires_on', 'registration_expires_on', 'meter_type', 'current_meter', 'meter_updated_at',
        'photo_path', 'manual_path', 'notes', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'warranty_expires_on' => 'date',
            'registration_expires_on' => 'date',
            'current_meter' => 'decimal:2',
            'meter_updated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->qr_token ??= (string) Str::uuid();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'asset_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'asset_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class, 'asset_id');
    }

    public function fleetPlanMemberships(): HasMany
    {
        return $this->hasMany(MaintenanceFleetPlanAsset::class, 'asset_id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MaintenanceMeterReading::class, 'asset_id');
    }

    public function partsUsed(): HasManyThrough
    {
        return $this->hasManyThrough(
            MaintenanceWorkOrderPart::class,
            MaintenanceWorkOrder::class,
            'asset_id',
            'work_order_id',
        );
    }

    public function getQrUrlAttribute(): string
    {
        return route('maintenance.assets.portal', $this->qr_token);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->asset_tag} — {$this->name}";
    }

    public function generateQrCode(int $size = 600): string
    {
        return (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            validateResult: false,
            data: $this->qr_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\KanbanCardScanned;
use Illuminate\Support\Facades\Notification;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;

class KanbanCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'bin_number',
        'bin_location',
        'reorder_point',
        'status',
        'last_scanned_at',
        'scanned_by_user_id',
        'department',
        'description'
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
        'reorder_point' => 'decimal:2'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING_ORDER = 'pending_order';
    const STATUS_ORDERED = 'ordered';

    public static function getStatuses()
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PENDING_ORDER,
            self::STATUS_ORDERED,
        ];
    }

    // Relationships
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    // Scopes
    public function scopePendingOrder($query)
    {
        return $query->where('status', self::STATUS_PENDING_ORDER);
    }

    // Methods
    public function markAsScanned()
    {
        $this->update([
            'status' => self::STATUS_PENDING_ORDER,
            'last_scanned_at' => now(),
            'scanned_by_user_id' => auth()->id()
        ]);

        // Send notification to admins
        // $admins = User::where('is_admin', true)->get();
        $admins = User::where('email', 'tchristensen@christyvault.com')->get();
        Notification::send($admins, new KanbanCardScanned($this));
    }

    public function canBeScanned(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Generate a QR code for this kanban card
     * @param string $size 'large', 'standard', or 'small'
     */
    public function generateQrCode(string $size = 'standard'): string
    {
        // Force a full URL with https://
        $scanUrl = config('app.url') . "/kanban-cards/{$this->id}/scan?inventory_item_id={$this->inventory_item_id}";

        // Log the URL for debugging
        Log::info('Generated QR URL:', ['url' => $scanUrl]);

        // Set QR code size based on card size
        $qrSize = match ($size) {
            'large' => 1500,    // 8.5" x 11" card
            'small' => 800,     // 3" x 5" card
            default => 1200,    // 5" x 7" card
        };

        $builder = new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true
            ],
            validateResult: false,
            data: $scanUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $qrSize,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        return $builder->build()->getString();
    }
}

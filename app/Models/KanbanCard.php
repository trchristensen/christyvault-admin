<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\KanbanCardScanned;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\KanbanCardQuantityUpdated;

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
        'description',
        'unit_of_measure',
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

    public function preferredSupplier()
    {
        return $this->inventoryItem->preferredSupplier();
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
        return DB::transaction(function () {
            // Get the preferred supplier
            $preferredSupplier = $this->preferredSupplier();
            if ($preferredSupplier) {
                $purchaseOrder = PurchaseOrder::create([
                    'supplier_id' => $preferredSupplier->id,
                    'status' => 'draft',
                    'notes' => 'Created from Kanban card scan',
                    'order_date' => now(),
                    'expected_delivery_date' => now()->addDays($preferredSupplier->lead_time ?? 7),
                ]);

                // Attach the inventory item to the purchase order
                $purchaseOrder->items()->attach($this->inventory_item_id, [
                    'inventory_item_id' => $this->inventory_item_id,
                    'supplier_id' => $preferredSupplier->id,
                    'quantity' => $this->quantity ? $this->quantity : 1,
                    'unit_price' => $preferredSupplier->pivot->unit_price ?? 0,
                    'total_price' => ($this->quantity * ($preferredSupplier->pivot->unit_price ?? 0)),
                    'received_quantity' => 0,
                ]);

                // Send Filament notification for purchase order creation
                 \Filament\Notifications\Notification::make()
                    ->title('Purchase Order Created')
                    ->body("Purchase order created for {$this->inventoryItem->name}")
                    ->success()
                    ->send();
            }

            // Update the kanban card
            $this->update([
                'last_scanned_at' => now(),
                'status' => self::STATUS_PENDING_ORDER,
                'scan_token' => Str::random(32),
            ]);

            // Send notification
            event(new KanbanCardScanned($this));

            return $this;
        });
    }

    public function updateQuantity(float $quantity)
    {
        // Flash notification for immediate feedback
        \Filament\Notifications\Notification::make()
            ->title('Kanban Card Scanned')
            ->body("Card scanned for {$this->inventoryItem->name} - Remaining: {$quantity} {$this->unit_of_measure}")
            ->success()
            ->send();

        // Database notification
        $users = User::where('email', 'tchristensen@christyvault.com')->get();
        LaravelNotification::send($users, new KanbanCardQuantityUpdated($this, $quantity));
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
        $scanUrl = route('kanban-cards.scan', [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'token' => $this->scan_token
        ]);

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

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($kanbanCard) {
            $kanbanCard->scan_token = Str::random(32);
        });
    }

    public function refreshScanToken(): void
    {
        $this->update([
            'scan_token' => Str::random(32)
        ]);
    }

    public function getScanTokenAttribute($value)
    {
        // If no token exists, generate one
        if (empty($value)) {
            $token = Str::random(32);
            $this->update(['scan_token' => $token]);
            return $token;
        }
        return $value;
    }
}

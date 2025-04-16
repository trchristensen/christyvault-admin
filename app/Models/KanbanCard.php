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
use Filament\Facades\Filament;

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
            
            if (!$preferredSupplier) {
                \Filament\Notifications\Notification::make()
                    ->title('Error')
                    ->body("No preferred supplier found for {$this->inventoryItem->name}")
                    ->danger()
                    ->send();
                return $this;
            }

            // Update the kanban card status
            $this->update([
                'last_scanned_at' => now(),
                'status' => self::STATUS_PENDING_ORDER,
                'scanned_by_user_id' => Auth::id(),
                'scan_token' => Str::random(32),
            ]);

            // Create a new purchase order
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $preferredSupplier->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => now(),
                'total_amount' => 0,
                'created_by_user_id' => Auth::id(),
                'is_liner_load' => $preferredSupplier->name === 'Wilbert'
            ]);

            try {
                // Add the item to the purchase order
                $this->addToPurchaseOrder($purchaseOrder, 1, true); // Suppress duplicate notifications

                // Show success notification
                \Filament\Notifications\Notification::make()
                    ->title('Kanban Card Scanned')
                    ->body("Created purchase order for {$this->inventoryItem->name}")
                    ->success()
                    ->send();

                // Send database notification
                $users = User::where('email', 'tchristensen@christyvault.com')->get();
                LaravelNotification::send($users, new KanbanCardScanned($this));

            } catch (\Exception $e) {
                Log::error('Failed to add item to purchase order', [
                    'kanban_card_id' => $this->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'error' => $e->getMessage()
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Error')
                    ->body("Failed to add {$this->inventoryItem->name} to purchase order")
                    ->danger()
                    ->send();
            }

            return $this;
        });
    }

    public function addToPurchaseOrder(PurchaseOrder $purchaseOrder, ?int $quantity = null, bool $suppressNotifications = false)
    {
        return DB::transaction(function () use ($purchaseOrder, $quantity, $suppressNotifications) {
            $preferredSupplier = $this->preferredSupplier();
            
            if (!$preferredSupplier || $purchaseOrder->supplier_id !== $preferredSupplier->id) {
                throw new \Exception('Invalid supplier for this purchase order');
            }

            // Check if the item is already in the PO
            $existingItem = $purchaseOrder->items()
                ->where('inventory_item_id', $this->inventory_item_id)
                ->first();

            if ($existingItem) {
                // Update the quantity if the item already exists
                $newQuantity = $existingItem->pivot->quantity + ($quantity ?: 1);
                $purchaseOrder->items()->updateExistingPivot($this->inventory_item_id, [
                    'quantity' => $newQuantity,
                    'total_price' => ($newQuantity * ($preferredSupplier->pivot->unit_price ?? 0)),
                ]);
            } else {
                // Attach the inventory item to the purchase order
                $purchaseOrder->items()->attach($this->inventory_item_id, [
                    'inventory_item_id' => $this->inventory_item_id,
                    'supplier_id' => $preferredSupplier->id,
                    'quantity' => $quantity ?: 1,
                    'unit_price' => $preferredSupplier->pivot->unit_price ?? 0,
                    'total_price' => (($quantity ?: 1) * ($preferredSupplier->pivot->unit_price ?? 0)),
                    'received_quantity' => 0,
                ]);
            }

            // Only send notification if not suppressed
            if (!$suppressNotifications) {
                \Filament\Notifications\Notification::make()
                    ->title('Purchase Order Updated')
                    ->body("Added {$this->inventoryItem->name} to purchase order")
                    ->success()
                    ->send();
            }

            return $purchaseOrder;
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

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
        // Only proceed if the card can be scanned
        if (!$this->canBeScanned()) {
            return false;
        }

        DB::transaction(function () {
            // Update the kanban card
            $this->update([
                'status' => self::STATUS_PENDING_ORDER,
                'last_scanned_at' => now(),
                'scanned_by_user_id' => Auth::id()
            ]);

            // Get the preferred supplier
            $supplier = $this->inventoryItem->preferredSupplier();

            if (!$supplier) {
                throw new \Exception('Cannot create purchase order: No preferred supplier set for this inventory item.');
            }

            // Get the cost per unit from the supplier pivot
            $quantity = $this->reorder_point ?? 1;
            $costPerUnit = $supplier->pivot->cost_per_unit ?? 0;
            $totalAmount = $quantity * $costPerUnit;

            // Create a new purchase order
            $purchaseOrder = PurchaseOrder::create([
                'status' => 'draft',
                'supplier_id' => $supplier->id ?? 1,
                'created_by_user_id' => Auth::id(),
                'total_amount' => $totalAmount,
                'notes' => 'Created from Kanban card scan'
            ]);

            // Insert the purchase order item
            DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $purchaseOrder->id,
                'inventory_item_id' => $this->inventory_item_id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => $supplier->pivot->supplier_sku,
                'quantity' => $quantity,
                'unit_price' => $costPerUnit,
                'total_price' => $totalAmount,
                'received_quantity' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Send notification
            $admins = User::where('email', 'tchristensen@christyvault.com')->get();
            LaravelNotification::send($admins, new KanbanCardScanned($this));
        });

        return true;
    }

    public function updateQuantity(float $quantity)
    {
        // Use Filament's notification system
        Notification::make()
            ->title('Kanban Card Scanned')
            ->body("Card scanned for {$this->inventoryItem->name} - Remaining: {$quantity} {$this->unit_of_measure}")
            ->success()
            ->icon('heroicon-o-qr-code')
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(url("/operations/resources/kanban-cards/{$this->id}")),
            ])
            ->database()
            ->send();
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

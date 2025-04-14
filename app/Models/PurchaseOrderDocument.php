<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDocument extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'type',
        'document_number',
        'file_path',
        'notes',
    ];

    // Document type constants
    const TYPE_INVOICE = 'invoice';
    const TYPE_BILL_OF_LADING = 'bill_of_lading';
    const TYPE_QUOTE = 'quote';
    const TYPE_OTHER = 'other';

    public static function getTypes(): array
    {
        return [
            self::TYPE_INVOICE => 'Invoice',
            self::TYPE_BILL_OF_LADING => 'Bill of Lading',
            self::TYPE_QUOTE => 'Quote',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}

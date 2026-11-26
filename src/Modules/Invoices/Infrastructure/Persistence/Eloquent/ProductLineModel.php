<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $invoice_id
 * @property int $position
 * @property string $name
 * @property int $quantity
 * @property int $unit_price
 */
final class ProductLineModel extends Model
{
    use HasUuids;

    protected $table = 'invoice_product_lines';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'integer',
    ];
}

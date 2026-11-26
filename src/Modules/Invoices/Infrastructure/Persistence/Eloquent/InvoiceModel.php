<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $status
 */
final class InvoiceModel extends Model
{
    use HasUuids;

    protected $table = 'invoices';

    protected $guarded = [];

    /** @return HasMany<ProductLineModel, $this> */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductLineModel::class, 'invoice_id')->orderBy('position');
    }
}

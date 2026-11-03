<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Mcp\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for add_part_orderdetail: one new supplier order detail (supplier, supplier part number and optional price
 * breaks) on an existing part. The part's existing orderdetails are left untouched.
 */
readonly class AddPartOrderdetailInput
{
    /**
     * @param array<int, array<string, mixed>> $pricedetails Raw price-break items, converted via PricedetailInput::fromArray()
     */
    public function __construct(
        /** The database ID of the part to add the new orderdetail to. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $partId,
        /** The database ID of the supplier this part can be ordered from. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $supplierId,
        /** The supplier's own part number (SKU / order number) for this part. */
        public ?string $supplierPartNr = null,
        /** URL to the part's page on the supplier's website. */
        public ?string $supplierProductUrl = null,
        /** Set to true if this part can no longer be ordered from this supplier. */
        public ?bool $obsolete = null,
        /** Set to true if the given prices include VAT. */
        public ?bool $pricesIncludesVat = null,
        /** Optional price breaks, each an object with "price" (required, a decimal string like "0.15"), and optionally "currencyId", "priceRelatedQuantity" (the quantity the price is for, default 1) and "minDiscountQuantity" (the minimum order quantity for this price, default 1). */
        public array $pricedetails = [],
        /** Optional message for the audit log; not a field of the orderdetail itself. */
        public ?string $logComment = null,
    ) {
    }

    public function toOrderdetailInput(): OrderdetailInput
    {
        return new OrderdetailInput(
            id: null,
            supplierId: $this->supplierId,
            supplierPartNr: $this->supplierPartNr,
            obsolete: $this->obsolete,
            supplierProductUrl: $this->supplierProductUrl,
            pricesIncludesVat: $this->pricesIncludesVat,
            pricedetails: $this->pricedetails,
        );
    }
}

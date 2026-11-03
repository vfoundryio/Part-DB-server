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
 * Input for add_part_lot: one new stock lot on an existing part. Every field besides "partId" is optional and, if
 * omitted, the lot is created with its normal default value for that field. The part's existing lots are left
 * untouched.
 */
readonly class AddPartLotInput
{
    public function __construct(
        /** The database ID of the part to add the new stock lot to. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $partId,
        /** The database ID of the storage location the new lot is stored at. */
        public ?int $storageLocationId = null,
        /** The amount of stock in the new lot. Leave it out (and set instockUnknown) if the amount is not known. */
        #[Assert\PositiveOrZero]
        public ?float $amount = null,
        /** Set to true if the amount of stock in the new lot is unknown (e.g. "a bag full of resistors"). */
        public ?bool $instockUnknown = null,
        public ?string $description = null,
        public ?string $comment = null,
        /** ISO 8601 date after which the parts in this lot expire. */
        public ?string $expirationDate = null,
        public ?bool $needsRefill = null,
        /** The database ID of the user owning the new lot. Only the owner can then withdraw, add or move its stock. */
        public ?int $ownerId = null,
        public ?string $userBarcode = null,
        /** Optional message for the audit log; not a field of the lot itself. */
        public ?string $logComment = null,
    ) {
    }

    public function toPartLotInput(): PartLotInput
    {
        return new PartLotInput(
            id: null,
            description: $this->description,
            comment: $this->comment,
            expirationDate: $this->expirationDate,
            storageLocationId: $this->storageLocationId,
            instockUnknown: $this->instockUnknown,
            amount: $this->amount,
            needsRefill: $this->needsRefill,
            ownerId: $this->ownerId,
            userBarcode: $this->userBarcode,
        );
    }
}

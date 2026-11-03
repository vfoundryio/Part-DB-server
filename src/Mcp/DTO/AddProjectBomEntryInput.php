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
 * Input for add_project_bom_entry: one new entry in an existing project's BOM, either for a part (partId) or a
 * non-part entry like "PCB fabrication" (name, optionally with a price). The project's existing BOM entries are left
 * untouched.
 */
readonly class AddProjectBomEntryInput
{
    public function __construct(
        /** The database ID of the project whose BOM the entry is added to. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $projectId,
        /** The database ID of the part this entry is for. Omit for a non-part entry, which then needs a "name". A part can only be in a BOM once. */
        #[Assert\Positive]
        public ?int $partId = null,
        /** The name of the entry. Required for non-part entries, optional for part entries. Must be unique within the BOM. */
        public ?string $name = null,
        /** How many of the part one build of the project needs. Defaults to 1. Rounded to a whole number unless the part's measurement unit allows decimals. */
        #[Assert\Positive]
        public float $quantity = 1.0,
        /** Comma-separated names of the places the part is mounted at, e.g. "R1,R2,R5". If given, their number must match the quantity. */
        public ?string $mountnames = null,
        public ?string $comment = null,
        /** The price of a non-part entry, as a decimal string like "12.50". Not allowed on part entries, whose price comes from the part's orderdetails. */
        public ?string $price = null,
        /** The database ID of the currency of "price". Omit for the base currency. */
        #[Assert\Positive]
        public ?int $priceCurrencyId = null,
        /** Optional message for the audit log; not a field of the BOM entry itself. */
        public ?string $logComment = null,
    ) {
    }
}

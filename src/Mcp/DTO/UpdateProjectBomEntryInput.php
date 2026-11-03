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
 * Input for update_project_bom_entry. Every field besides "id" is optional and a field that is *omitted* from the
 * tool call leaves the corresponding BOM entry property untouched (see wasProvided()). Only quantity, mountnames and
 * comment can be changed; the part (or name) an entry is for stays fixed.
 */
final readonly class UpdateProjectBomEntryInput
{
    /**
     * @param array<string, bool> $providedFields Which top-level keys were actually present in the raw MCP arguments
     */
    public function __construct(
        /** The database ID of the BOM entry to update, as returned by get_project_details or add_project_bom_entry. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $id,
        /** The new quantity. Cannot be cleared. */
        public ?float $quantity,
        /** Comma-separated names of the places the part is mounted at, e.g. "R1,R2,R5"; their number must match the quantity. Pass null (or "") to clear. */
        public ?string $mountnames,
        /** Pass null (or "") to clear. */
        public ?string $comment,
        /** Optional message for the audit log; not a field of the BOM entry itself. */
        public ?string $logComment,
        private array $providedFields,
    ) {
    }

    public function wasProvided(string $field): bool
    {
        return $this->providedFields[$field] ?? false;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            quantity: PartInputHelpers::float($data, 'quantity'),
            mountnames: PartInputHelpers::str($data, 'mountnames'),
            comment: PartInputHelpers::str($data, 'comment'),
            logComment: PartInputHelpers::str($data, 'logComment'),
            providedFields: array_fill_keys(array_keys($data), true),
        );
    }
}

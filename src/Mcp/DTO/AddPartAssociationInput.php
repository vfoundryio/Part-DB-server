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
 * Input for add_part_association: one new association from an existing part (the owner) to another part. The
 * part's existing associations are left untouched.
 */
readonly class AddPartAssociationInput
{
    public function __construct(
        /** The database ID of the part that owns the new association (e.g. the newer part that supersedes another). Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $partId,
        /** The database ID of the other part of the association. Required, and must differ from partId. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $otherPartId,
        /** How the two parts are related: "COMPATIBLE" (the part is compatible with the other part), "SUPERSEDES" (the part is a newer version of the other part) or "OTHER" (described by otherType). Required. */
        #[Assert\NotBlank]
        public string $type,
        /** A short, user-defined description of the relation. Required if type is "OTHER". */
        public ?string $otherType = null,
        public ?string $comment = null,
        /** Optional message for the audit log; not a field of the association itself. */
        public ?string $logComment = null,
    ) {
    }

    public function toAssociatedPartInput(): AssociatedPartInput
    {
        return new AssociatedPartInput(
            id: null,
            otherPartId: $this->otherPartId,
            type: $this->type,
            otherType: $this->otherType,
            comment: $this->comment,
        );
    }
}

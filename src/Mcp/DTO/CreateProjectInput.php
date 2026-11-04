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
 * Input for create_project. Only "name" is required; every other field is optional and, if omitted, the project is
 * created with its normal default value for that field. Shares the generic structural-element fields (comment,
 * notSelectable, parentId, alternativeNames) with create_category & co. and adds the project-specific description
 * and status.
 */
final readonly class CreateProjectInput extends AbstractStructuralElementWriteInput
{
    public function __construct(
        /** The name of the new project. Required, cannot be empty. */
        #[Assert\NotBlank]
        public string $name,
        /** A longer description of the project. */
        public ?string $description,
        /** The status of the project: one of "draft", "planning", "in_production", "finished" or "archived". Omit to leave it unset. */
        public ?string $status,
        ?string $comment,
        ?bool $notSelectable,
        ?int $parentId,
        ?string $alternativeNames,
        ?string $logComment,
        array $providedFields,
    ) {
        parent::__construct(
            comment: $comment,
            notSelectable: $notSelectable,
            parentId: $parentId,
            alternativeNames: $alternativeNames,
            logComment: $logComment,
            providedFields: $providedFields,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ...self::extractSharedFields($data),
            name: (string) ($data['name'] ?? ''),
            description: PartInputHelpers::str($data, 'description'),
            status: PartInputHelpers::str($data, 'status'),
            providedFields: array_fill_keys(array_keys($data), true),
        );
    }
}

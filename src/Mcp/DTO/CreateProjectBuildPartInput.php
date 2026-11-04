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
 * Input for create_project_build_part. Only `projectId` is required. The new part starts out the same way the web
 * UI's "create build part" button initializes it (name and description copied from the project, tagged
 * "project-build"); every other field takes the same values as create_part, and any field given here overrides that
 * initial value. See AbstractPartWriteInput for the shared fields.
 */
final readonly class CreateProjectBuildPartInput extends AbstractPartWriteInput
{
    /**
     * @param int         $projectId The database ID of the project the new part represents the builds of. Required; the project must not have a build part yet.
     * @param string|null $name      The name of the new part. Defaults to the project's name.
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $projectId,
        public ?string $name,
        ?string $description,
        ?string $comment,
        ?bool $favorite,
        ?int $categoryId,
        ?int $footprintId,
        ?int $manufacturerId,
        ?string $manufacturerProductUrl,
        ?string $manufacturerProductNumber,
        ?string $manufacturingStatus,
        ?float $minAmount,
        ?int $partUnitId,
        ?bool $needsReview,
        ?string $tags,
        ?float $mass,
        ?string $ipn,
        ?int $partCustomStateId,
        ?string $gtin,
        ?int $masterPictureAttachmentId,
        array $partLots,
        array $parameters,
        array $orderdetails,
        array $associatedPartsAsOwner,
        ?EdaInfoInput $edaInfo,
        ?string $providerKey,
        ?string $providerId,
        ?string $providerUrl,
        ?string $logComment,
        array $providedFields,
    ) {
        parent::__construct(
            description: $description,
            comment: $comment,
            favorite: $favorite,
            categoryId: $categoryId,
            footprintId: $footprintId,
            manufacturerId: $manufacturerId,
            manufacturerProductUrl: $manufacturerProductUrl,
            manufacturerProductNumber: $manufacturerProductNumber,
            manufacturingStatus: $manufacturingStatus,
            minAmount: $minAmount,
            partUnitId: $partUnitId,
            needsReview: $needsReview,
            tags: $tags,
            mass: $mass,
            ipn: $ipn,
            partCustomStateId: $partCustomStateId,
            gtin: $gtin,
            masterPictureAttachmentId: $masterPictureAttachmentId,
            partLots: $partLots,
            parameters: $parameters,
            orderdetails: $orderdetails,
            associatedPartsAsOwner: $associatedPartsAsOwner,
            edaInfo: $edaInfo,
            providerKey: $providerKey,
            providerId: $providerId,
            providerUrl: $providerUrl,
            logComment: $logComment,
            providedFields: $providedFields,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ...self::extractSharedFields($data),
            projectId: (int) ($data['projectId'] ?? 0),
            name: PartInputHelpers::str($data, 'name'),
            providedFields: array_fill_keys(array_keys($data), true),
        );
    }
}

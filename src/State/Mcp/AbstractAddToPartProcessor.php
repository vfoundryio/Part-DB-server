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

namespace App\State\Mcp;

use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Parts\Part;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Shared by the additive add_part_lot/add_part_parameter/add_part_orderdetail/add_part_association processors.
 * Unlike update_part, which reconciles whole nested collections (omitting an existing item removes it), these
 * tools only ever append exactly one new row to an existing part and never touch the part's other rows - so a
 * caller can add to a part without first having to read back and resend everything it already has.
 *
 * Field application is shared with create_part/update_part via the apply*Fields() helpers of
 * AbstractPartMutationProcessor; the editing-enabled guard and process()/error-wrapping are inherited from there too.
 */
abstract class AbstractAddToPartProcessor extends AbstractPartMutationProcessor
{
    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        EventCommentHelper $eventCommentHelper,
        McpSettings $mcpSettings,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($entityManager, $validator, $eventCommentHelper, $mcpSettings);
    }

    /**
     * Resolves the part to add to and checks "edit" on it - the same check the web UI does before adding a lot
     * (PartController::addLot()) or saving the part edit form the other rows are added through.
     */
    protected function resolveEditablePart(int $id): Part
    {
        $part = $this->entityManager->find(Part::class, $id);
        if (!$part instanceof Part) {
            throw new NotFoundHttpException(sprintf('Part with id %d not found.', $id));
        }

        //Manual check - the McpTool's `security` attribute is not enforced against the object by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('edit', $part)) {
            throw new AccessDeniedException(sprintf('Access denied to part with id %d.', $id));
        }

        return $part;
    }

    /**
     * Validates the whole part (its collections are Assert\Valid, so this covers the newly added row and the
     * part-level uniqueness rules, e.g. for parameter names), feeds the log comment into the audit log and flushes.
     */
    protected function validateAndFlush(Part $part, ?string $logComment): void
    {
        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($part);

        $this->applyLogComment($logComment);

        $this->entityManager->flush();
    }
}

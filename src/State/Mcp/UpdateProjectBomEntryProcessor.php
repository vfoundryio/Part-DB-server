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

use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Mcp\DTO\UpdateProjectBomEntryInput;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Partially updates an existing BOM entry's quantity, mountnames and comment, mirroring
 * ProjectController::editBOMEntry(): the same "edit" permission on the BOM entry (i.e. on its project, see
 * BOMEntryVoter). Only fields that were actually present in the raw MCP arguments are applied. The editing-enabled
 * guard and process()/error-wrapping are inherited - see AbstractProjectBomEntryMutationProcessor.
 */
final class UpdateProjectBomEntryProcessor extends AbstractProjectBomEntryMutationProcessor
{
    protected function mutateBomEntry(mixed $data): ProjectBOMEntry
    {
        if (!$data instanceof UpdateProjectBomEntryInput) {
            throw new \InvalidArgumentException('Expected UpdateProjectBomEntryInput');
        }

        $entry = $this->findOrFail(ProjectBOMEntry::class, $data->id);

        //Manual check - the McpTool's `security` attribute is not enforced against the object by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('edit', $entry)) {
            throw new AccessDeniedException(sprintf('Access denied to edit BOM entry with id %d.', $data->id));
        }

        if ($data->wasProvided('quantity')) {
            if ($data->quantity === null) {
                throw new BadRequestHttpException('The quantity of a BOM entry cannot be cleared.');
            }
            $entry->setQuantity($data->quantity);
        }
        if ($data->wasProvided('mountnames')) {
            $entry->setMountnames($data->mountnames ?? '');
        }
        if ($data->wasProvided('comment')) {
            $entry->setComment($data->comment ?? '');
        }

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($entry);

        $this->applyLogComment($data->logComment);

        $this->entityManager->flush();

        return $entry;
    }
}

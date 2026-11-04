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

use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Mcp\DTO\AddProjectBomEntryInput;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Adds one new entry to an existing project's BOM, mirroring ProjectController::addPart() (the "add parts to project"
 * form): the same "edit" permission on the project, and the same rule that a part (or a name) can only be in a BOM
 * once. The project's existing BOM entries are never touched. The editing-enabled guard and process()/error-wrapping
 * are inherited - see AbstractProjectBomEntryMutationProcessor.
 */
final class AddProjectBomEntryProcessor extends AbstractProjectBomEntryMutationProcessor
{
    protected function mutateBomEntry(mixed $data): ProjectBOMEntry
    {
        if (!$data instanceof AddProjectBomEntryInput) {
            throw new \InvalidArgumentException('Expected AddProjectBomEntryInput');
        }

        $project = $this->findOrFail(Project::class, $data->projectId);

        //Manual check - the McpTool's `security` attribute is not enforced against the object by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('edit', $project)) {
            throw new AccessDeniedException(sprintf('Access denied to edit project with id %d.', $data->projectId));
        }

        $entry = new ProjectBOMEntry();

        //Same uniqueness rules as the UniqueEntity constraints of the web UI's add-parts form (ProjectAddPartsType)
        if ($data->partId !== null) {
            $part = $this->findOrFail(Part::class, $data->partId);
            $this->assertNotInBom($project, 'part', $part, sprintf('Part with id %d', $data->partId));
            $entry->setPart($part);
        }
        if ($data->name !== null && $data->name !== '') {
            $this->assertNotInBom($project, 'name', $data->name, sprintf('An entry named "%s"', $data->name));
            $entry->setName($data->name);
        }

        $entry->setQuantity($data->quantity);
        if ($data->mountnames !== null) {
            $entry->setMountnames($data->mountnames);
        }
        if ($data->comment !== null) {
            $entry->setComment($data->comment);
        }
        if ($data->price !== null) {
            try {
                $entry->setPrice(BigDecimal::of($data->price));
            } catch (MathException) {
                throw new BadRequestHttpException(sprintf('"%s" is not a valid price.', $data->price));
            }
        }
        if ($data->priceCurrencyId !== null) {
            $entry->setPriceCurrency($this->findOrFail(Currency::class, $data->priceCurrencyId));
        }

        $project->addBomEntry($entry);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($entry);

        $this->applyLogComment($data->logComment);

        $this->entityManager->flush();

        return $entry;
    }

    private function assertNotInBom(Project $project, string $field, mixed $value, string $what): void
    {
        $existing = $project->getBomEntries()->matching(Criteria::create()->where(Criteria::expr()->eq($field, $value)))->first();
        if ($existing instanceof ProjectBOMEntry) {
            throw new BadRequestHttpException(sprintf('%s is already in the BOM of project %d (BOM entry id %d); use update_project_bom_entry to change it.',
                $what, $project->getID(), $existing->getID()));
        }
    }
}

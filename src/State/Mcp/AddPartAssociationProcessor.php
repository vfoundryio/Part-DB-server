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

use App\Entity\Parts\PartAssociation;
use App\Mcp\DTO\AddPartAssociationInput;

/**
 * Adds one new association from an existing part to another part, with the same "edit" permission on the owning
 * part the web UI's part edit form requires. The part's existing associations are never touched. Everything else is
 * inherited - see AbstractAddToPartProcessor.
 */
final class AddPartAssociationProcessor extends AbstractAddToPartProcessor
{
    protected function mutatePart(mixed $data): PartAssociation
    {
        if (!$data instanceof AddPartAssociationInput) {
            throw new \InvalidArgumentException('Expected AddPartAssociationInput');
        }

        $part = $this->resolveEditablePart($data->partId);

        $association = new PartAssociation();
        $this->applyAssociationFields($association, $data->toAssociatedPartInput());
        $part->addAssociatedPartsAsOwner($association);

        $this->validateAndFlush($part, $data->logComment);

        return $association;
    }
}

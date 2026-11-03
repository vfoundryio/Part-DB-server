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

use App\Entity\Parts\PartLot;
use App\Mcp\DTO\AddPartLotInput;

/**
 * Adds one new stock lot to an existing part, mirroring PartController::addLot() (the "add lot" dialog on the part
 * info page): the same "edit" permission on the part, and the new lot may be created with an initial amount. The
 * part's existing lots are never touched. Everything else is inherited - see AbstractAddToPartProcessor.
 */
final class AddPartLotProcessor extends AbstractAddToPartProcessor
{
    protected function mutatePart(mixed $data): PartLot
    {
        if (!$data instanceof AddPartLotInput) {
            throw new \InvalidArgumentException('Expected AddPartLotInput');
        }

        $part = $this->resolveEditablePart($data->partId);

        $lot = new PartLot();
        $this->applyPartLotFields($lot, $data->toPartLotInput(), allowAmount: true);
        $part->addPartLot($lot);

        $this->validateAndFlush($part, $data->logComment);

        return $lot;
    }
}

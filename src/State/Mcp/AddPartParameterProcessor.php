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

use App\Entity\Parameters\PartParameter;
use App\Mcp\DTO\AddPartParameterInput;

/**
 * Adds one new parameter to an existing part, with the same "edit" permission on the part the web UI's part edit
 * form requires. The part's existing parameters are never touched. Everything else is inherited - see
 * AbstractAddToPartProcessor.
 */
final class AddPartParameterProcessor extends AbstractAddToPartProcessor
{
    protected function mutatePart(mixed $data): PartParameter
    {
        if (!$data instanceof AddPartParameterInput) {
            throw new \InvalidArgumentException('Expected AddPartParameterInput');
        }

        $part = $this->resolveEditablePart($data->partId);

        $parameter = new PartParameter();
        $this->applyParameterFields($parameter, $data->toParameterInput());
        $part->addParameter($parameter);

        $this->validateAndFlush($part, $data->logComment);

        return $parameter;
    }
}

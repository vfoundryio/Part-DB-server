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

use App\Entity\PriceInformations\Orderdetail;
use App\Mcp\DTO\AddPartOrderdetailInput;

/**
 * Adds one new orderdetail (with its optional price breaks) to an existing part, with the same "edit" permission on
 * the part the web UI's part edit form requires. The part's existing orderdetails are never touched. Everything else
 * is inherited - see AbstractAddToPartProcessor.
 */
final class AddPartOrderdetailProcessor extends AbstractAddToPartProcessor
{
    protected function mutatePart(mixed $data): Orderdetail
    {
        if (!$data instanceof AddPartOrderdetailInput) {
            throw new \InvalidArgumentException('Expected AddPartOrderdetailInput');
        }

        $part = $this->resolveEditablePart($data->partId);

        $orderdetail = new Orderdetail();
        $this->applyOrderdetailFields($orderdetail, $data->toOrderdetailInput());
        $part->addOrderdetail($orderdetail);

        $this->validateAndFlush($part, $data->logComment);

        return $orderdetail;
    }
}

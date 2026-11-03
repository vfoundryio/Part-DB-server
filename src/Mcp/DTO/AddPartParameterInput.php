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
 * Input for add_part_parameter: one new parameter (e.g. "Voltage: 5V typ.") on an existing part. The part's existing
 * parameters are left untouched.
 */
readonly class AddPartParameterInput
{
    public function __construct(
        /** The database ID of the part to add the new parameter to. Required. */
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $partId,
        /** The name of the parameter, e.g. "Voltage". Required; must be unique within its group on this part. */
        #[Assert\NotBlank]
        public string $name,
        /** Optional group the parameter is shown in, e.g. "Electrical". */
        public ?string $group = null,
        /** Optional formula symbol, e.g. "V_CC". */
        public ?string $symbol = null,
        public ?float $valueMin = null,
        public ?float $valueTypical = null,
        public ?float $valueMax = null,
        /** The unit of the numeric values, e.g. "V". */
        public ?string $unit = null,
        /** A text value, for parameters that are not numeric. */
        public ?string $valueText = null,
        /** Optional message for the audit log; not a field of the parameter itself. */
        public ?string $logComment = null,
    ) {
    }

    public function toParameterInput(): ParameterInput
    {
        return new ParameterInput(
            id: null,
            name: $this->name,
            group: $this->group,
            symbol: $this->symbol,
            valueMin: $this->valueMin,
            valueTypical: $this->valueTypical,
            valueMax: $this->valueMax,
            unit: $this->unit,
            valueText: $this->valueText,
        );
    }
}

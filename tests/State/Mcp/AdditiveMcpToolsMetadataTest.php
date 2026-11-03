<?php

declare(strict_types=1);

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

namespace App\Tests\State\Mcp;

use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The additive part/project write tools must be registered with the MCP server, gated by the collection-level
 * permission the matching web UI action needs (the `security` expression also decides whether a caller sees the
 * tool in tools/list at all), and must never be advertised as destructive.
 */
class AdditiveMcpToolsMetadataTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function provideTools(): iterable
    {
        yield 'add_part_lot' => [PartLot::class, 'add_part_lot', 'is_granted("@parts.edit")'];
        yield 'add_part_parameter' => [AbstractParameter::class, 'add_part_parameter', 'is_granted("@parts.edit")'];
        yield 'add_part_orderdetail' => [Orderdetail::class, 'add_part_orderdetail', 'is_granted("@parts.edit")'];
        yield 'add_part_association' => [PartAssociation::class, 'add_part_association', 'is_granted("@parts.edit")'];
        yield 'create_project' => [Project::class, 'create_project', 'is_granted("@projects.create")'];
        yield 'create_project_build_part' => [Part::class, 'create_project_build_part', 'is_granted("@parts.create")'];
        yield 'add_project_bom_entry' => [ProjectBOMEntry::class, 'add_project_bom_entry', 'is_granted("@projects.edit")'];
        yield 'update_project_bom_entry' => [ProjectBOMEntry::class, 'update_project_bom_entry', 'is_granted("@projects.edit")'];
    }

    #[DataProvider('provideTools')]
    public function testToolIsRegisteredNonDestructiveWithExpectedSecurity(string $resourceClass, string $toolName, string $expectedSecurity): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $tool = null;
        foreach ($container->get(ResourceMetadataCollectionFactoryInterface::class)->create($resourceClass) as $resource) {
            $tool = $resource->getMcp()[$toolName] ?? $tool;
        }
        self::assertInstanceOf(McpTool::class, $tool, sprintf('%s should declare the MCP tool "%s"', $resourceClass, $toolName));
        self::assertSame($expectedSecurity, $tool->getSecurity());
        self::assertSame(false, $tool->getAnnotations()['readOnlyHint'] ?? null);
        self::assertSame(false, $tool->getAnnotations()['destructiveHint'] ?? null);

        //Service ids, see CallToolResultToolsDeclareNoOutputSchemaTest
        $container->get('mcp.server.default.builder')->build();
        $registered = $container->get('mcp.server.default.registry')->getTool($toolName)->tool;
        self::assertSame($toolName, $registered->name);
        self::assertArrayHasKey('logComment', $registered->inputSchema['properties']);
    }
}

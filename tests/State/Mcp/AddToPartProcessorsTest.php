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

use ApiPlatform\Metadata\Post;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\AssociationType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\AddPartAssociationInput;
use App\Mcp\DTO\AddPartLotInput;
use App\Mcp\DTO\AddPartOrderdetailInput;
use App\Mcp\DTO\AddPartParameterInput;
use App\Repository\LogEntryRepository;
use App\Settings\AISettings\McpSettings;
use App\State\Mcp\AddPartAssociationProcessor;
use App\State\Mcp\AddPartLotProcessor;
use App\State\Mcp\AddPartOrderdetailProcessor;
use App\State\Mcp\AddPartParameterProcessor;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the additive add_part_lot/add_part_parameter/add_part_orderdetail/add_part_association tools: each one
 * appends exactly one new row to an existing part, returns that row (with its id), and leaves every row the part
 * already had in place - unlike update_part, whose nested collections are reconciled as a whole.
 */
class AddToPartProcessorsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private McpSettings $mcpSettings;
    private Part $part;
    private Part $otherPart;
    private StorageLocation $storageLocation;
    private Supplier $supplier;

    /** @var array{lot: int, parameter: int, orderdetail: int, pricedetail: int, association: int} */
    private array $existingIds;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        //Editing MCP tools is disabled by default (McpSettings::$editingEnabled) - enable it for these tests,
        //which exercise the tools' actual behavior; testDeniesAccessWhenEditingToolsDisabled() covers the guard itself.
        $this->mcpSettings = self::getContainer()->get(McpSettings::class);
        $this->mcpSettings->editingEnabled = true;

        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        $storageLocation = $this->em->getRepository(StorageLocation::class)->findOneBy(['name' => 'Node 1']);
        $supplier = $this->em->getRepository(Supplier::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);
        self::assertNotNull($storageLocation);
        self::assertNotNull($supplier);
        $this->storageLocation = $storageLocation;
        $this->supplier = $supplier;

        $this->otherPart = new Part();
        $this->otherPart->setName('MCP Add Test Other Part');
        $this->otherPart->setCategory($category);
        $this->em->persist($this->otherPart);

        //The part starts out with one row of every kind, so each test can prove that row survives the addition
        $this->part = new Part();
        $this->part->setName('MCP Add Test Part');
        $this->part->setCategory($category);

        $lot = new PartLot();
        $lot->setDescription('Existing lot');
        $lot->setAmount(3.0);
        $lot->setStorageLocation($storageLocation);
        $this->part->addPartLot($lot);

        $parameter = new PartParameter();
        $parameter->setName('Existing parameter');
        $parameter->setValueTypical(1.0);
        $this->part->addParameter($parameter);

        $orderdetail = new Orderdetail();
        $orderdetail->setSupplier($supplier);
        $orderdetail->setSupplierpartnr('EXISTING-1');
        $pricedetail = new Pricedetail();
        $pricedetail->setPrice(BigDecimal::of('1.00'));
        $orderdetail->addPricedetail($pricedetail);
        $this->part->addOrderdetail($orderdetail);

        $association = new PartAssociation();
        $association->setOther($this->otherPart);
        $association->setType(AssociationType::COMPATIBLE);
        $this->part->addAssociatedPartsAsOwner($association);

        $this->em->persist($this->part);
        $this->em->flush();

        $this->existingIds = [
            'lot' => $lot->getID(),
            'parameter' => $parameter->getID(),
            'orderdetail' => $orderdetail->getID(),
            'pricedetail' => $pricedetail->getID(),
            'association' => $association->getID(),
        ];
    }

    /**
     * Expected failures (permission denied, not found, bad input) are reported as a successful CallToolResult
     * with isError:true and an explanatory text - not as a thrown exception. See McpToolErrorHandling.
     */
    private function assertErrorResult(mixed $result, string $messageContains): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString($messageContains, $result->content[0]->text);
    }

    /**
     * Re-reads the part from the database (not the identity map) and returns the ids of its rows per collection.
     *
     * @return array{lots: list<int>, parameters: list<int>, orderdetails: list<int>, associations: list<int>}
     */
    private function reloadRowIds(): array
    {
        $partId = $this->part->getID();
        $this->em->clear();
        $part = $this->em->find(Part::class, $partId);
        self::assertInstanceOf(Part::class, $part);

        $ids = static fn (iterable $rows): array => array_values(array_map(static fn ($row) => $row->getID(), [...$rows]));

        return [
            'lots' => $ids($part->getPartLots()),
            'parameters' => $ids($part->getParameters()),
            'orderdetails' => $ids($part->getOrderdetails()),
            'associations' => $ids($part->getAssociatedPartsAsOwner()),
        ];
    }

    private function assertExistingRowsUntouched(array $rowIds): void
    {
        self::assertContains($this->existingIds['lot'], $rowIds['lots']);
        self::assertContains($this->existingIds['parameter'], $rowIds['parameters']);
        self::assertContains($this->existingIds['orderdetail'], $rowIds['orderdetails']);
        self::assertContains($this->existingIds['association'], $rowIds['associations']);
    }

    public function testAddPartLotAppendsLotAndKeepsExistingOnes(): void
    {
        /** @var AddPartLotProcessor $processor */
        $processor = self::getContainer()->get(AddPartLotProcessor::class);
        $result = $processor->process(new AddPartLotInput(
            partId: $this->part->getID(),
            storageLocationId: $this->storageLocation->getID(),
            amount: 7.0,
            description: 'New lot',
        ), new Post());

        self::assertInstanceOf(PartLot::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame(7.0, $result->getAmount());
        self::assertSame('New lot', $result->getDescription());
        self::assertSame($this->storageLocation, $result->getStorageLocation());
        self::assertSame($this->part, $result->getPart());

        $rowIds = $this->reloadRowIds();
        self::assertCount(2, $rowIds['lots']);
        self::assertContains($result->getID(), $rowIds['lots']);
        $this->assertExistingRowsUntouched($rowIds);
        self::assertSame(3.0, $this->em->find(PartLot::class, $this->existingIds['lot'])->getAmount());
    }

    public function testAddPartLotWithUnknownStock(): void
    {
        /** @var AddPartLotProcessor $processor */
        $processor = self::getContainer()->get(AddPartLotProcessor::class);
        $result = $processor->process(new AddPartLotInput(
            partId: $this->part->getID(),
            storageLocationId: $this->storageLocation->getID(),
            instockUnknown: true,
        ), new Post());

        self::assertInstanceOf(PartLot::class, $result);
        self::assertTrue($result->isInstockUnknown());
    }

    public function testAddPartParameterAppendsParameterAndKeepsExistingOnes(): void
    {
        /** @var AddPartParameterProcessor $processor */
        $processor = self::getContainer()->get(AddPartParameterProcessor::class);
        $result = $processor->process(new AddPartParameterInput(
            partId: $this->part->getID(),
            name: 'Voltage',
            group: 'Electrical',
            valueTypical: 5.0,
            unit: 'V',
        ), new Post());

        self::assertInstanceOf(PartParameter::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame('Voltage', $result->getName());
        self::assertSame('Electrical', $result->getGroup());
        self::assertSame(5.0, $result->getValueTypical());
        self::assertSame('V', $result->getUnit());

        $rowIds = $this->reloadRowIds();
        self::assertCount(2, $rowIds['parameters']);
        $this->assertExistingRowsUntouched($rowIds);
    }

    public function testAddPartParameterRejectsDuplicateNameWithoutTouchingExistingOne(): void
    {
        /** @var AddPartParameterProcessor $processor */
        $processor = self::getContainer()->get(AddPartParameterProcessor::class);
        $result = $processor->process(new AddPartParameterInput(
            partId: $this->part->getID(),
            name: 'Existing parameter',
            valueTypical: 99.0,
        ), new Post());

        $this->assertErrorResult($result, 'already used');

        $rowIds = $this->reloadRowIds();
        self::assertSame([$this->existingIds['parameter']], $rowIds['parameters']);
        self::assertSame(1.0, $this->em->find(PartParameter::class, $this->existingIds['parameter'])->getValueTypical());
    }

    public function testAddPartOrderdetailAppendsOrderdetailWithPriceBreaksAndKeepsExistingOnes(): void
    {
        /** @var AddPartOrderdetailProcessor $processor */
        $processor = self::getContainer()->get(AddPartOrderdetailProcessor::class);
        $result = $processor->process(new AddPartOrderdetailInput(
            partId: $this->part->getID(),
            supplierId: $this->supplier->getID(),
            supplierPartNr: 'NEW-123',
            pricedetails: [
                ['price' => '0.15', 'minDiscountQuantity' => 1],
                ['price' => '0.10', 'minDiscountQuantity' => 100],
            ],
        ), new Post());

        self::assertInstanceOf(Orderdetail::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame($this->supplier, $result->getSupplier());
        self::assertSame('NEW-123', $result->getSupplierPartNr());
        self::assertCount(2, $result->getPricedetails());
        foreach ($result->getPricedetails() as $pricedetail) {
            self::assertNotNull($pricedetail->getID());
        }

        $rowIds = $this->reloadRowIds();
        self::assertCount(2, $rowIds['orderdetails']);
        $this->assertExistingRowsUntouched($rowIds);
        //The existing orderdetail keeps its own price break
        $existing = $this->em->find(Orderdetail::class, $this->existingIds['orderdetail']);
        self::assertSame([$this->existingIds['pricedetail']], array_map(static fn (Pricedetail $p) => $p->getID(), $existing->getPricedetails()->toArray()));
    }

    public function testAddPartAssociationAppendsAssociationAndKeepsExistingOnes(): void
    {
        $thirdPart = new Part();
        $thirdPart->setName('MCP Add Test Third Part');
        $thirdPart->setCategory($this->part->getCategory());
        $this->em->persist($thirdPart);
        $this->em->flush();

        /** @var AddPartAssociationProcessor $processor */
        $processor = self::getContainer()->get(AddPartAssociationProcessor::class);
        $result = $processor->process(new AddPartAssociationInput(
            partId: $this->part->getID(),
            otherPartId: $thirdPart->getID(),
            type: 'SUPERSEDES',
            comment: 'Newer revision',
        ), new Post());

        self::assertInstanceOf(PartAssociation::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame($this->part, $result->getOwner());
        self::assertSame($thirdPart, $result->getOther());
        self::assertSame(AssociationType::SUPERSEDES, $result->getType());

        $rowIds = $this->reloadRowIds();
        self::assertCount(2, $rowIds['associations']);
        $this->assertExistingRowsUntouched($rowIds);
    }

    public function testAddPartAssociationRejectsSelfAssociation(): void
    {
        /** @var AddPartAssociationProcessor $processor */
        $processor = self::getContainer()->get(AddPartAssociationProcessor::class);
        $result = $processor->process(new AddPartAssociationInput(
            partId: $this->part->getID(),
            otherPartId: $this->part->getID(),
            type: 'COMPATIBLE',
        ), new Post());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame([$this->existingIds['association']], $this->reloadRowIds()['associations']);
    }

    public function testLogCommentIsWrittenToTheAuditLog(): void
    {
        /** @var AddPartLotProcessor $processor */
        $processor = self::getContainer()->get(AddPartLotProcessor::class);
        $lot = $processor->process(new AddPartLotInput(
            partId: $this->part->getID(),
            amount: 1.0,
            logComment: 'Restocked via MCP',
        ), new Post());
        self::assertInstanceOf(PartLot::class, $lot);

        /** @var LogEntryRepository $logRepository */
        $logRepository = $this->em->getRepository(AbstractLogEntry::class);
        $created = array_values(array_filter(
            $logRepository->getElementHistory($lot),
            static fn ($entry) => $entry instanceof ElementCreatedLogEntry
        ));
        self::assertCount(1, $created);
        self::assertSame('Restocked via MCP', $created[0]->getComment());
    }

    public function testThrowsNotFoundForUnknownPart(): void
    {
        $container = self::getContainer();

        $this->assertErrorResult($container->get(AddPartLotProcessor::class)->process(
            new AddPartLotInput(partId: 999999999, amount: 1.0), new Post()), 'not found');
        $this->assertErrorResult($container->get(AddPartParameterProcessor::class)->process(
            new AddPartParameterInput(partId: 999999999, name: 'X'), new Post()), 'not found');
        $this->assertErrorResult($container->get(AddPartOrderdetailProcessor::class)->process(
            new AddPartOrderdetailInput(partId: 999999999, supplierId: $this->supplier->getID()), new Post()), 'not found');
        $this->assertErrorResult($container->get(AddPartAssociationProcessor::class)->process(
            new AddPartAssociationInput(partId: 999999999, otherPartId: $this->otherPart->getID(), type: 'COMPATIBLE'), new Post()), 'not found');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideDeniedUsers(): iterable
    {
        //No permissions at all
        yield 'noread' => ['noread'];
        //Can read parts but not edit them: proves the tools check "edit" on the part, not just "read"
        yield 'readonly' => ['readonly'];
    }

    #[DataProvider('provideDeniedUsers')]
    public function testDeniesAccessForUserWithoutEditPermissionAndAddsNothing(string $userKind): void
    {
        $this->client->loginUser($this->getDeniedUser($userKind));
        $container = self::getContainer();
        $partId = $this->part->getID();

        $this->assertErrorResult($container->get(AddPartLotProcessor::class)->process(
            new AddPartLotInput(partId: $partId, amount: 1.0), new Post()), 'Access denied');
        $this->assertErrorResult($container->get(AddPartParameterProcessor::class)->process(
            new AddPartParameterInput(partId: $partId, name: 'Should not be added'), new Post()), 'Access denied');
        $this->assertErrorResult($container->get(AddPartOrderdetailProcessor::class)->process(
            new AddPartOrderdetailInput(partId: $partId, supplierId: $this->supplier->getID()), new Post()), 'Access denied');
        $this->assertErrorResult($container->get(AddPartAssociationProcessor::class)->process(
            new AddPartAssociationInput(partId: $partId, otherPartId: $this->otherPart->getID(), type: 'COMPATIBLE'), new Post()), 'Access denied');

        self::assertSame([
            'lots' => [$this->existingIds['lot']],
            'parameters' => [$this->existingIds['parameter']],
            'orderdetails' => [$this->existingIds['orderdetail']],
            'associations' => [$this->existingIds['association']],
        ], $this->reloadRowIds());
    }

    public function testDeniesAccessWhenEditingToolsDisabled(): void
    {
        $this->mcpSettings->editingEnabled = false;

        /** @var AddPartLotProcessor $processor */
        $processor = self::getContainer()->get(AddPartLotProcessor::class);
        $result = $processor->process(new AddPartLotInput(partId: $this->part->getID(), amount: 1.0), new Post());

        $this->assertErrorResult($result, 'Editing via MCP tools is disabled');
    }

    private function getDeniedUser(string $userKind): User
    {
        if ($userKind === 'noread') {
            $user = $this->em->getRepository(User::class)->findOneBy(['name' => 'noread']);
            self::assertNotNull($user);

            return $user;
        }

        $group = $this->em->getRepository(Group::class)->findOneBy(['name' => 'readonly']);
        self::assertNotNull($group);
        $user = new User();
        $user->setName('mcp_readonly_test_user');
        $user->setGroup($group);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}

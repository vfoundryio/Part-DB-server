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

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\AddProjectBomEntryInput;
use App\Mcp\DTO\CreateProjectBuildPartInput;
use App\Mcp\DTO\CreateProjectInput;
use App\Mcp\DTO\UpdateProjectBomEntryInput;
use App\Settings\AISettings\McpSettings;
use App\State\Mcp\AddProjectBomEntryProcessor;
use App\State\Mcp\CreateProjectBuildPartProcessor;
use App\State\Mcp\CreateProjectProcessor;
use App\State\Mcp\UpdateProjectBomEntryProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the project/BOM write tools: create_project, create_project_build_part, add_project_bom_entry and
 * update_project_bom_entry. None of them may remove or replace a row that already exists.
 */
class ProjectMutationProcessorsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private McpSettings $mcpSettings;
    private Category $category;
    private Project $project;
    private Part $partA;
    private Part $partB;
    private ProjectBOMEntry $existingEntry;

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
        self::assertNotNull($category);
        $this->category = $category;

        $this->partA = new Part();
        $this->partA->setName('MCP BOM Test Part A');
        $this->partA->setCategory($category);
        $this->em->persist($this->partA);

        $this->partB = new Part();
        $this->partB->setName('MCP BOM Test Part B');
        $this->partB->setCategory($category);
        $this->em->persist($this->partB);

        $this->project = new Project();
        $this->project->setName('MCP BOM Test Project');
        $this->project->setDescription('A project for testing');

        $this->existingEntry = new ProjectBOMEntry();
        $this->existingEntry->setPart($this->partA);
        $this->existingEntry->setQuantity(2);
        $this->existingEntry->setMountnames('R1,R2');
        $this->existingEntry->setComment('Existing comment');
        $this->project->addBomEntry($this->existingEntry);

        $this->em->persist($this->project);
        $this->em->flush();
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
     * Re-reads the test project from the database (not the identity map).
     */
    private function reloadProject(): Project
    {
        $id = $this->project->getID();
        $this->em->clear();
        $project = $this->em->find(Project::class, $id);
        self::assertInstanceOf(Project::class, $project);

        return $project;
    }

    /**
     * @return list<int>
     */
    private function bomEntryIds(Project $project): array
    {
        return array_values(array_map(static fn (ProjectBOMEntry $e) => $e->getID(), $project->getBomEntries()->toArray()));
    }

    public function testCreateProjectWithAllFields(): void
    {
        $parent = $this->em->getRepository(Project::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($parent);

        /** @var CreateProjectProcessor $processor */
        $processor = self::getContainer()->get(CreateProjectProcessor::class);
        $result = $processor->process(CreateProjectInput::fromArray([
            'name' => 'MCP Created Project',
            'description' => 'Created via MCP',
            'status' => 'planning',
            'comment' => 'A comment',
            'parentId' => $parent->getID(),
        ]), (new Post())->withClass(Project::class));

        self::assertInstanceOf(Project::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame('MCP Created Project', $result->getName());
        self::assertSame('Created via MCP', $result->getDescription());
        self::assertSame('planning', $result->getStatus());
        self::assertSame('A comment', $result->getComment());
        self::assertSame($parent, $result->getParent());
    }

    public function testCreateProjectRejectsInvalidStatus(): void
    {
        /** @var CreateProjectProcessor $processor */
        $processor = self::getContainer()->get(CreateProjectProcessor::class);
        $result = $processor->process(CreateProjectInput::fromArray([
            'name' => 'MCP Invalid Status Project',
            'status' => 'not_a_status',
        ]), (new Post())->withClass(Project::class));

        $this->assertErrorResult($result, 'status');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Project::class)->findOneBy(['name' => 'MCP Invalid Status Project']));
    }

    public function testCreateProjectBuildPartInitializesPartFromProject(): void
    {
        /** @var CreateProjectBuildPartProcessor $processor */
        $processor = self::getContainer()->get(CreateProjectBuildPartProcessor::class);
        $result = $processor->process(CreateProjectBuildPartInput::fromArray([
            'projectId' => $this->project->getID(),
            'categoryId' => $this->category->getID(),
        ]), new Post());

        self::assertInstanceOf(Part::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame('MCP BOM Test Project', $result->getName());
        self::assertSame('A project for testing', $result->getDescription());
        self::assertSame('project-build', $result->getTags());

        $project = $this->reloadProject();
        self::assertSame($result->getID(), $project->getBuildPart()?->getID());
        //Creating the build part does not touch the BOM
        self::assertSame([$this->existingEntry->getID()], $this->bomEntryIds($project));
    }

    public function testCreateProjectBuildPartAcceptsCreatePartFieldsAsOverrides(): void
    {
        /** @var CreateProjectBuildPartProcessor $processor */
        $processor = self::getContainer()->get(CreateProjectBuildPartProcessor::class);
        $result = $processor->process(CreateProjectBuildPartInput::fromArray([
            'projectId' => $this->project->getID(),
            'categoryId' => $this->category->getID(),
            'name' => 'Assembled board',
            'tags' => 'project-build,assembled',
            'partLots' => [['amount' => 2.0]],
        ]), new Post());

        self::assertInstanceOf(Part::class, $result);
        self::assertSame('Assembled board', $result->getName());
        self::assertSame('project-build,assembled', $result->getTags());
        self::assertCount(1, $result->getPartLots());
        self::assertSame($this->project, $result->getBuiltProject());
    }

    public function testCreateProjectBuildPartNeverReplacesAnExistingBuildPart(): void
    {
        /** @var CreateProjectBuildPartProcessor $processor */
        $processor = self::getContainer()->get(CreateProjectBuildPartProcessor::class);
        $first = $processor->process(CreateProjectBuildPartInput::fromArray([
            'projectId' => $this->project->getID(),
            'categoryId' => $this->category->getID(),
        ]), new Post());
        self::assertInstanceOf(Part::class, $first);
        $firstId = $first->getID();

        $second = $processor->process(CreateProjectBuildPartInput::fromArray([
            'projectId' => $this->project->getID(),
            'categoryId' => $this->category->getID(),
            'name' => 'Should not be created',
        ]), new Post());

        $this->assertErrorResult($second, 'already has a build part');
        $project = $this->reloadProject();
        self::assertSame($firstId, $project->getBuildPart()?->getID());
        self::assertNull($this->em->getRepository(Part::class)->findOneBy(['name' => 'Should not be created']));
    }

    public function testAddBomEntryAppendsEntryAndKeepsExistingOnes(): void
    {
        /** @var AddProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(AddProjectBomEntryProcessor::class);
        $result = $processor->process(new AddProjectBomEntryInput(
            projectId: $this->project->getID(),
            partId: $this->partB->getID(),
            quantity: 3,
            mountnames: 'C1,C2,C3',
            comment: 'Decoupling',
        ), new Post());

        self::assertInstanceOf(ProjectBOMEntry::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame($this->partB, $result->getPart());
        self::assertSame(3.0, $result->getQuantity());
        self::assertSame('C1,C2,C3', $result->getMountnames());
        self::assertSame('Decoupling', $result->getComment());

        $project = $this->reloadProject();
        self::assertEqualsCanonicalizing([$this->existingEntry->getID(), $result->getID()], $this->bomEntryIds($project));
        $existing = $this->em->find(ProjectBOMEntry::class, $this->existingEntry->getID());
        self::assertSame(2.0, $existing->getQuantity());
        self::assertSame('R1,R2', $existing->getMountnames());
    }

    public function testAddBomEntryRejectsPartAlreadyInBom(): void
    {
        /** @var AddProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(AddProjectBomEntryProcessor::class);
        $result = $processor->process(new AddProjectBomEntryInput(
            projectId: $this->project->getID(),
            partId: $this->partA->getID(),
            quantity: 5,
        ), new Post());

        $this->assertErrorResult($result, 'already in the BOM');
        $project = $this->reloadProject();
        self::assertSame([$this->existingEntry->getID()], $this->bomEntryIds($project));
        self::assertSame(2.0, $this->em->find(ProjectBOMEntry::class, $this->existingEntry->getID())->getQuantity());
    }

    public function testAddBomEntryCreatesNonPartEntryWithPrice(): void
    {
        /** @var AddProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(AddProjectBomEntryProcessor::class);
        $result = $processor->process(new AddProjectBomEntryInput(
            projectId: $this->project->getID(),
            name: 'PCB fabrication',
            price: '12.50',
        ), new Post());

        self::assertInstanceOf(ProjectBOMEntry::class, $result);
        self::assertNull($result->getPart());
        self::assertSame('PCB fabrication', $result->getName());
        self::assertSame('12.50', (string) $result->getPrice());
    }

    public function testAddBomEntryRejectsEntryWithNeitherPartNorName(): void
    {
        /** @var AddProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(AddProjectBomEntryProcessor::class);
        $result = $processor->process(new AddProjectBomEntryInput(projectId: $this->project->getID()), new Post());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame([$this->existingEntry->getID()], $this->bomEntryIds($this->reloadProject()));
    }

    public function testAddBomEntryRejectsInvalidPrice(): void
    {
        /** @var AddProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(AddProjectBomEntryProcessor::class);
        $result = $processor->process(new AddProjectBomEntryInput(
            projectId: $this->project->getID(),
            name: 'Assembly',
            price: 'twelve',
        ), new Post());

        $this->assertErrorResult($result, 'not a valid price');
    }

    public function testUpdateBomEntryOnlyChangesProvidedFields(): void
    {
        /** @var UpdateProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(UpdateProjectBomEntryProcessor::class);
        $result = $processor->process(UpdateProjectBomEntryInput::fromArray([
            'id' => $this->existingEntry->getID(),
            'comment' => 'Changed comment',
        ]), new Patch());

        self::assertInstanceOf(ProjectBOMEntry::class, $result);
        self::assertSame($this->existingEntry->getID(), $result->getID());
        self::assertSame('Changed comment', $result->getComment());
        self::assertSame(2.0, $result->getQuantity());
        self::assertSame('R1,R2', $result->getMountnames());
        self::assertSame($this->partA, $result->getPart());

        $result = $processor->process(UpdateProjectBomEntryInput::fromArray([
            'id' => $this->existingEntry->getID(),
            'quantity' => 3,
            'mountnames' => 'R1,R2,R3',
        ]), new Patch());

        self::assertInstanceOf(ProjectBOMEntry::class, $result);
        self::assertSame(3.0, $result->getQuantity());
        self::assertSame('R1,R2,R3', $result->getMountnames());
        self::assertSame('Changed comment', $result->getComment());
        self::assertSame([$this->existingEntry->getID()], $this->bomEntryIds($this->reloadProject()));
    }

    public function testUpdateBomEntryRejectsMountnamesNotMatchingQuantity(): void
    {
        /** @var UpdateProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(UpdateProjectBomEntryProcessor::class);
        $result = $processor->process(UpdateProjectBomEntryInput::fromArray([
            'id' => $this->existingEntry->getID(),
            'quantity' => 4,
        ]), new Patch());

        $this->assertErrorResult($result, 'mountnames');
        $this->reloadProject();
        self::assertSame(2.0, $this->em->find(ProjectBOMEntry::class, $this->existingEntry->getID())->getQuantity());
    }

    public function testUpdateBomEntryRejectsClearingQuantity(): void
    {
        /** @var UpdateProjectBomEntryProcessor $processor */
        $processor = self::getContainer()->get(UpdateProjectBomEntryProcessor::class);
        $result = $processor->process(UpdateProjectBomEntryInput::fromArray([
            'id' => $this->existingEntry->getID(),
            'quantity' => null,
        ]), new Patch());

        $this->assertErrorResult($result, 'cannot be cleared');
    }

    public function testThrowsNotFoundForUnknownIds(): void
    {
        $container = self::getContainer();

        $this->assertErrorResult($container->get(CreateProjectBuildPartProcessor::class)->process(
            CreateProjectBuildPartInput::fromArray(['projectId' => 999999999, 'categoryId' => $this->category->getID()]), new Post()), 'not found');
        $this->assertErrorResult($container->get(AddProjectBomEntryProcessor::class)->process(
            new AddProjectBomEntryInput(projectId: 999999999, partId: $this->partB->getID()), new Post()), 'not found');
        $this->assertErrorResult($container->get(AddProjectBomEntryProcessor::class)->process(
            new AddProjectBomEntryInput(projectId: $this->project->getID(), partId: 999999999), new Post()), 'not found');
        $this->assertErrorResult($container->get(UpdateProjectBomEntryProcessor::class)->process(
            UpdateProjectBomEntryInput::fromArray(['id' => 999999999, 'comment' => 'x']), new Patch()), 'not found');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideDeniedUsers(): iterable
    {
        //No permissions at all
        yield 'noread' => ['noread'];
        //Can read projects and parts but not create/edit them: proves the tools check the write permission
        yield 'readonly' => ['readonly'];
    }

    #[DataProvider('provideDeniedUsers')]
    public function testDeniesAccessForUserWithoutWritePermissionAndChangesNothing(string $userKind): void
    {
        $this->client->loginUser($this->getDeniedUser($userKind));
        $container = self::getContainer();

        $this->assertErrorResult($container->get(CreateProjectProcessor::class)->process(
            CreateProjectInput::fromArray(['name' => 'MCP Denied Project']), (new Post())->withClass(Project::class)), 'not allowed');
        $this->assertErrorResult($container->get(CreateProjectBuildPartProcessor::class)->process(
            CreateProjectBuildPartInput::fromArray(['projectId' => $this->project->getID(), 'categoryId' => $this->category->getID()]), new Post()), 'not allowed');
        $this->assertErrorResult($container->get(AddProjectBomEntryProcessor::class)->process(
            new AddProjectBomEntryInput(projectId: $this->project->getID(), partId: $this->partB->getID()), new Post()), 'Access denied');
        $this->assertErrorResult($container->get(UpdateProjectBomEntryProcessor::class)->process(
            UpdateProjectBomEntryInput::fromArray(['id' => $this->existingEntry->getID(), 'quantity' => 5]), new Patch()), 'Access denied');

        $project = $this->reloadProject();
        self::assertNull($project->getBuildPart());
        self::assertSame([$this->existingEntry->getID()], $this->bomEntryIds($project));
        self::assertSame(2.0, $this->em->find(ProjectBOMEntry::class, $this->existingEntry->getID())->getQuantity());
        self::assertNull($this->em->getRepository(Project::class)->findOneBy(['name' => 'MCP Denied Project']));
    }

    public function testDeniesAccessWhenEditingToolsDisabled(): void
    {
        $this->mcpSettings->editingEnabled = false;
        $container = self::getContainer();

        $this->assertErrorResult($container->get(CreateProjectProcessor::class)->process(
            CreateProjectInput::fromArray(['name' => 'MCP Disabled Project']), (new Post())->withClass(Project::class)), 'Editing via MCP tools is disabled');
        $this->assertErrorResult($container->get(AddProjectBomEntryProcessor::class)->process(
            new AddProjectBomEntryInput(projectId: $this->project->getID(), partId: $this->partB->getID()), new Post()), 'Editing via MCP tools is disabled');
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

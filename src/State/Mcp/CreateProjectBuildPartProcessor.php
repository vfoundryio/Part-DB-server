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

use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Mcp\DTO\CreateProjectBuildPartInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\ProjectSystem\ProjectBuildPartHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Creates the build part of a project (the part that represents the project's finished builds in stock), mirroring
 * the web UI's "create build part" button (PartController::new() via the part_new_build_part route): the part is
 * initialized by ProjectBuildPartHelper and needs the same "create" permission on parts.
 *
 * A project that already has a build part is rejected rather than re-linked: Project::$build_part has
 * orphanRemoval, so replacing it would delete the previous build part (and its stock). Field application, the
 * editing-enabled guard and process()/error-wrapping are inherited - see AbstractPartMutationProcessor.
 */
final class CreateProjectBuildPartProcessor extends AbstractPartMutationProcessor
{
    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        EventCommentHelper $eventCommentHelper,
        McpSettings $mcpSettings,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ProjectBuildPartHelper $projectBuildPartHelper,
    ) {
        parent::__construct($entityManager, $validator, $eventCommentHelper, $mcpSettings);
    }

    protected function mutatePart(mixed $data): Part
    {
        if (!$data instanceof CreateProjectBuildPartInput) {
            throw new \InvalidArgumentException('Expected CreateProjectBuildPartInput');
        }

        $project = $this->entityManager->find(Project::class, $data->projectId);
        if (!$project instanceof Project) {
            throw new NotFoundHttpException(sprintf('Project with id %d not found.', $data->projectId));
        }

        $existing = $project->getBuildPart();
        if ($existing instanceof Part) {
            throw new BadRequestHttpException(sprintf('Project with id %d already has a build part (part id %d).', $data->projectId, $existing->getID()));
        }

        //Manual check - the same "create" permission on parts the web UI's "create build part" route requires.
        //Checked before ProjectBuildPartHelper links the new part to the (managed) project.
        if (!$this->authorizationChecker->isGranted('create', Part::class)) {
            throw new AccessDeniedException('You are not allowed to create parts.');
        }

        $part = $this->projectBuildPartHelper->getPartInitialization($project);
        if ($data->wasProvided('name')) {
            $part->setName($data->name ?? '');
        }
        $this->applyProvidedFields($part, $data);

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($part);

        $this->applyLogComment($data->logComment);

        $this->entityManager->persist($part);
        $this->entityManager->flush();

        return $part;
    }
}

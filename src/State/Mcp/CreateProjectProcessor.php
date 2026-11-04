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

use ApiPlatform\Metadata\Operation;
use App\Entity\ProjectSystem\Project;
use App\Mcp\DTO\CreateProjectInput;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Creates a new project. The generic structural-element fields are applied exactly like CreateStructuralElementProcessor
 * does for categories & co.; description and status are the project-specific additions. The editing-enabled guard and
 * process()/error-wrapping are inherited - see AbstractStructuralElementMutationProcessor.
 */
final class CreateProjectProcessor extends AbstractStructuralElementMutationProcessor
{
    protected function mutateElement(mixed $data, Operation $operation): Project
    {
        if (!$data instanceof CreateProjectInput) {
            throw new \InvalidArgumentException('Expected CreateProjectInput');
        }

        //Manual check - the same "create" permission the web UI's new project form requires
        if (!$this->authorizationChecker->isGranted('create', Project::class)) {
            throw new AccessDeniedException('You are not allowed to create a Project.');
        }

        $project = new Project();
        $project->setName($data->name);
        $this->applyProvidedFields($project, $data);
        if ($data->wasProvided('description')) {
            $project->setDescription($data->description ?? '');
        }
        if ($data->wasProvided('status')) {
            $project->setStatus($data->status);
        }

        //Manual entity validation - the McpTool's `validate` flag only validates the input DTO, not the entity
        $this->validator->validate($project);

        $this->applyLogComment($data->logComment);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }
}

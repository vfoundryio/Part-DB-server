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
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Shared by AddProjectBomEntryProcessor and UpdateProjectBomEntryProcessor. Owns the process() method as a template:
 * the global editing-enabled guard and expected-error-to-CallToolResult wrapping (McpToolErrorHandling) happen
 * exactly once, here, so no subclass can forget them - each subclass only implements mutateBomEntry().
 */
abstract class AbstractProjectBomEntryMutationProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ValidatorInterface $validator,
        protected readonly EventCommentHelper $eventCommentHelper,
        protected readonly McpSettings $mcpSettings,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    final public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProjectBOMEntry|CallToolResult
    {
        return $this->runCatchingExpectedErrors(function () use ($data) {
            $this->mcpSettings->assertEditingEnabled();

            return $this->mutateBomEntry($data);
        });
    }

    abstract protected function mutateBomEntry(mixed $data): ProjectBOMEntry;

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function findOrFail(string $class, int $id): object
    {
        $entity = $this->entityManager->find($class, $id);
        if (!$entity instanceof $class) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found.', (new \ReflectionClass($class))->getShortName(), $id));
        }

        return $entity;
    }

    /**
     * Feeds a non-empty logComment into the audit log, shared by add/update.
     */
    protected function applyLogComment(?string $logComment): void
    {
        if ($logComment !== null && $logComment !== '' && !$this->eventCommentHelper->isMessageSet()) {
            $this->eventCommentHelper->setMessage($logComment);
        }
    }
}

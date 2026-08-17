<?php

declare(strict_types=1);

namespace App\tests\unit\module\company\domain\dto\role;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Application\DTO\Role\DeleteMultipleDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeleteMultipleRolesTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private MessageService $messageService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->validator = $container->get(ValidatorInterface::class);
        $this->messageService = $container->get(MessageService::class);
    }

    public function testValidUuidList(): void
    {
        $dto = new DeleteMultipleDTO();
        $dto->rolesUUIDs = [
            '8a1f1b4a-0c12-4b7d-91f7-25b25a1354b1',
            '123e4567-e89b-12d3-a456-426614174000',
        ];

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations);
    }

    public function testEmptyUuidList(): void
    {
        $dto = new DeleteMultipleDTO();
        $dto->rolesUUIDs = [];

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));

        $this->assertSame($this->messageService->get('role.delete.multiple.selectedUUIDsRequired', [], 'roles'), $violations[0]->getMessage());
    }

    public function testInvalidUuidInList(): void
    {
        $dto = new DeleteMultipleDTO();
        $dto->rolesUUIDs = [
            'not-a-uuid',
            '12345',
        ];

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));

        foreach ($violations as $violation) {
            $this->assertSame($this->messageService->get('uuid.invalid', [], 'validators'), $violation->getMessage());
        }
    }
}

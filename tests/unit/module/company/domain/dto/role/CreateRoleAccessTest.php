<?php

namespace App\tests\unit\module\company\domain\dto\role;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Application\DTO\Role\AssignAccessesDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateRoleAccessTest extends KernelTestCase
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

    public function testAccessUUIDCanBeBlank(): void
    {
        $dto = new AssignAccessesDTO();
        $dto->accessesUUIDs = [];

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testAccessUUIDMustContainValidUUIDs(): void
    {
        $dto = new AssignAccessesDTO();
        $dto->accessesUUIDs = ['invalid-uuid'];

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);

        $violation = $violations[0];
        $this->assertSame($this->messageService->get('uuid.invalid', [], 'validators'), $violation->getMessage());
    }
}

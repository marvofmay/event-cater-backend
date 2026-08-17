<?php

namespace App\tests\unit\module\company\domain\dto\role;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Application\DTO\Role\AssignPermissionsDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateRoleAccessPermissionTest extends KernelTestCase
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

    public function testAccessesCanBeBlank(): void
    {
        $dto = new AssignPermissionsDTO();
        $dto->accesses = [];

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));
    }
}

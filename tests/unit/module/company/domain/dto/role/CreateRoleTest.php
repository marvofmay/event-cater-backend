<?php

declare(strict_types=1);

namespace App\tests\unit\module\company\domain\dto\role;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Application\DTO\Role\CreateDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateRoleTest extends KernelTestCase
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

    public function testValidDto(): void
    {
        $dto = new CreateDTO();
        $dto->name = 'Some role';
        $dto->description = 'Des description ....';

        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors, 'DTO should not has errors');
    }

    public function testNameIsTooShort(): void
    {
        $dto = new CreateDTO();
        $dto->name = 'Ro';

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
        $this->assertSame($this->messageService->get('role.name.minimumLength', [':qty' => 3], 'roles'), $errors[0]->getMessage());
    }

    public function testNameIsBlank(): void
    {
        $dto = new CreateDTO();
        $dto->name = '';

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
        $this->assertSame($this->messageService->get('role.name.required', [], 'roles'), $errors[0]->getMessage());
    }

    public function testNameIsTooLong(): void
    {
        $dto = new CreateDTO();
        $dto->name = str_repeat('x', 120);

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
        $this->assertSame($this->messageService->get('role.name.maximumLength', [':qty' => 100], 'roles'), $errors[0]->getMessage());
    }
}

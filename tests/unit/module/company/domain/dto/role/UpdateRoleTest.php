<?php

declare(strict_types=1);

namespace App\tests\unit\module\company\domain\dto\role;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Application\DTO\Role\UpdateDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UpdateRoleTest extends KernelTestCase
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

    public function testValidData(): void
    {
        $dto = new UpdateDTO();
        $dto->name = 'Manager';
        $dto->description = 'Manages department';

        $violations = $this->validator->validate($dto);
        $this->assertCount(0, $violations, 'No validation errors should be present.');
    }

    public function testEmptyName(): void
    {
        $dto = new UpdateDTO();
        $dto->name = '';
        $dto->description = 'Optional description';

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));

        $this->assertSame($this->messageService->get('role.name.required', [], 'roles'), $violations[0]->getMessage());
    }

    public function testTooShortName(): void
    {
        $dto = new UpdateDTO();
        $dto->name = 'AB';

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));

        $this->assertSame($this->messageService->get('role.name.minimumLength', [':qty' => 3], 'roles'), $violations[0]->getMessage());
    }

    public function testTooLongName(): void
    {
        $dto = new UpdateDTO();
        $dto->name = str_repeat('A', 120);

        $violations = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($violations));

        $this->assertSame($this->messageService->get('role.name.maximumLength', [':qty' => 100], 'roles'), $violations[0]->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Projector;

use App\Module\Company\Domain\Event\Employee\EmployeeChangedAddressEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedContactEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedPersonalDataEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeCreatedEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeDeletedEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeRestoredEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeUpdatedEvent;
use App\Module\Company\Domain\Service\Email\EmailService;
use App\Module\Company\Domain\Service\Employee\EmployeeChangerAddress;
use App\Module\Company\Domain\Service\Employee\EmployeeChangerContact;
use App\Module\Company\Domain\Service\Employee\EmployeeChangerPersonalData;
use App\Module\Company\Domain\Service\Employee\EmployeeCreator;
use App\Module\Company\Domain\Service\Employee\EmployeeDeleter;
use App\Module\Company\Domain\Service\Employee\EmployeeRestorer;
use App\Module\Company\Domain\Service\Employee\EmployeeUpdater;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class EmployeeProjector
{
    public function __construct(
        private EmployeeCreator $employeeCreator,
        private EmployeeUpdater $employeeUpdater,
        private EmployeeDeleter $employeeDeleter,
        private EmployeeRestorer $employeeRestorer,
        private EmployeeChangerPersonalData $employeeChangerPersonalData,
        private EmployeeChangerAddress $employeeChangerAddress,
        private EmployeeChangerContact $employeeChangerContact,
        private EmailService $emailService,
        private Security $security,
    ) {
    }

    #[AsEventListener(event: EmployeeCreatedEvent::class)]
    public function onEmployeeCreated(EmployeeCreatedEvent $event): void
    {
        $this->employeeCreator->create($event);

        $this->emailService->sendEmail(
            recipients: $event->emails->toArray(),
            subject: sprintf('Witaj w firmie %s!', $event->firstName->getValue()),
            sender: $this->security->getUser(),
            templateName: 'emails/welcome.html.twig',
            context: [
                'firstName' => $event->firstName->getValue(),
                'login' => $event->emails->toArray()[0],
            ],
        );
    }

    #[AsEventListener(event: EmployeeUpdatedEvent::class)]
    public function onEmployeeUpdated(EmployeeUpdatedEvent $event): void
    {
        $this->employeeUpdater->update($event);
    }

    #[AsEventListener(event: EmployeeDeletedEvent::class)]
    public function onEmployeeDeleted(EmployeeDeletedEvent $event): void
    {
        $this->employeeDeleter->delete($event);
    }

    #[AsEventListener(event: EmployeeRestoredEvent::class)]
    public function onEmployeeRestored(EmployeeRestoredEvent $event): void
    {
        $this->employeeRestorer->restore($event);
    }

    #[AsEventListener(event: EmployeeChangedPersonalDataEvent::class)]
    public function onEmployeeChangedPersonalData(EmployeeChangedPersonalDataEvent $event): void
    {
        $this->employeeChangerPersonalData->change($event);
    }

    #[AsEventListener(event: EmployeeChangedAddressEvent::class)]
    public function onEmployeeChangedAddress(EmployeeChangedAddressEvent $event): void
    {
        $this->employeeChangerAddress->change($event);
    }

    #[AsEventListener(event: EmployeeChangedContactEvent::class)]
    public function onEmployeeChangedContact(EmployeeChangedContactEvent $event): void
    {
        $this->employeeChangerContact->change($event);
    }
}

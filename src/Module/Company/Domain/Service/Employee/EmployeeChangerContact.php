<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Employee;

use App\Common\Domain\Interface\DomainEventInterface;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Interface\Contact\ContactWriterInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;
use App\Module\Company\Domain\Service\Factory\ContactFactory;
use Doctrine\Common\Collections\Collection;

final readonly class EmployeeChangerContact
{
    public function __construct(
        private ContactFactory $contactFactory,
        private EmployeeWriterInterface $employeeWriterRepository,
        private EmployeeReaderInterface $employeeReaderRepository,
        private ContactWriterInterface $contactWriterRepository,
    ) {
    }

    public function change(DomainEventInterface $event): void
    {
        $employee = $this->employeeReaderRepository->getEmployeeByUUID($event->uuid->toString());
        $user = $employee->getUser();
        $contacts = $employee->getContacts();
        $email = $event->emails->toArray()[0];

        $this->deleteContacts($contacts);
        $contacts = $this->contactFactory->create($event->phones, $event->emails);

        if ($user->getEmail() !== $email) {
            $user->setEmail($email);
        }

        $this->setEmployeeContactRelation($employee, $contacts);

        $this->employeeWriterRepository->saveEmployee($employee);
    }

    private function deleteContacts(Collection $contacts): void
    {
        $this->contactWriterRepository->deleteContactsInDB($contacts, Contact::HARD_DELETED_AT);
    }

    private function setEmployeeContactRelation(Employee $employee, array $contacts): void
    {
        foreach ($contacts as $contact) {
            $employee->addContact($contact);
        }
    }
}

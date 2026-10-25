<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Factory;

use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Aggregate\ValueObject\Websites;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Enum\Contact\ContactTypeEnum;

class ContactFactory
{
    public function create(?Phones $phones, ?Emails $emails = null, ?Websites $websites = null): array
    {
        $contacts = [];

        $dataSets = [
            ContactTypeEnum::PHONE->value => $phones?->toArray() ?? [],
            ContactTypeEnum::EMAIL->value => $emails?->toArray() ?? [],
            ContactTypeEnum::WEBSITE->value => $websites?->toArray() ?? [],
        ];

        foreach ($dataSets as $type => $values) {
            foreach ($values as $value) {
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                $contact = new Contact();
                $contact->setType($type);
                $contact->setData($value);
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\Contact;

use App\Module\Company\Domain\Entity\Contact;

final readonly class ContactView
{
    public function __construct(
        public string $type,
        public string $data,
        public bool $active
    ) {
    }

    public static function fromContact(Contact $contact): self
    {
        return new self(
            type: $contact->getType(),
            data: $contact->getData(),
            active: $contact->isActive(),
        );
    }
}

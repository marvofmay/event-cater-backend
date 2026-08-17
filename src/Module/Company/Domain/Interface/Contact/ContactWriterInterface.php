<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Interface\Contact;

use Doctrine\Common\Collections\Collection;

interface ContactWriterInterface
{
    public function deleteContacts(Collection $contacts): void;
}

<?php

declare(strict_types=1);

namespace App\Module\System\Application\Validator\Access;

use App\Common\Domain\Interface\CommandInterface;
use App\Common\Domain\Interface\QueryInterface;
use App\Common\Domain\Interface\ValidatorInterface;
use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('app.role.assignAccesses.validator')]
final readonly class AccessesExistsValidator implements ValidatorInterface
{
    public function __construct(
        private AccessReaderInterface $accessReaderRepository,
        private TranslatorInterface $translator
    ) {
    }

    public function supports(CommandInterface|QueryInterface $data): bool
    {
        return true;
    }

    public function validate(CommandInterface|QueryInterface $data): void
    {
        $uuids = $data->accessesUUIDs ?? [];

        if (empty($uuids)) {
            return;
        }

        $foundRAccesses = $this->accessReaderRepository
            ->getAccessesByUUIDs($uuids)
            ->filter(fn ($access) => $access->getActive() && $access->getModule()->getActive())
            ->map(fn ($access) => $access->getUUID())
            ->toArray();

        $missing = array_diff($uuids, $foundRAccesses);

        if (!empty($missing)) {
            $translatedErrors = array_map(
                fn (string $uuid) => $this->translator->trans('access.uuid.notExists', [':uuid' => $uuid], 'accesses'),
                $missing
            );

            throw new \Exception(implode(', ', $translatedErrors), Response::HTTP_NOT_FOUND);
        }
    }
}

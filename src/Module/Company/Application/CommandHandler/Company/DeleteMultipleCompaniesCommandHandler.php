<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Company;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Company\DeleteMultipleCompaniesCommand;
use App\Module\Company\Domain\Aggregate\Company\CompanyAggregate;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Event\Company\CompanyMultipleDeletedEvent;
use App\Module\Company\Domain\Interface\Company\CompanyAggregateReaderInterface;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteMultipleCompaniesCommandHandler extends CommandHandlerAbstract
{
    use HandleEventStoreTrait;
    use ClassNameExtractorTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompanyAggregateReaderInterface $companyAggregateReaderRepository,
        private readonly EventStoreCreator $eventStoreCreator,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[AutowireIterator(tag: 'app.company.delete_multiple.validator')] protected iterable $validators,
    ) {
    }

    public function __invoke(DeleteMultipleCompaniesCommand $command): void
    {
        $this->validate($command);

        $deletedUUIDs = [];
        foreach ($command->companiesUUIDs as $companyUUID) {
            $uuid = CompanyUUID::fromString($companyUUID);
            $companyAggregate = $this->companyAggregateReaderRepository->getCompanyAggregateByUUID($uuid);
            $companyAggregate->delete();

            $events = $companyAggregate->pullEvents();
            foreach ($events as $event) {
                $this->handleEvent($event, CompanyAggregate::class);
            }

            $deletedUUIDs[] = $uuid->toString();
        }

        $user = $this->security->getUser();
        $userUUID = $user->getUuid();

        $multiEvent = new CompanyMultipleDeletedEvent($deletedUUIDs);
        $message = sprintf(
            'uuid: %s, eventClass: %s, aggregateClass: %s, data: %s, userUUID: %s',
            implode(',', $deletedUUIDs),
            $this->getShortClassName($multiEvent::class),
            CompanyAggregate::class,
            $this->serializer->serialize($multiEvent, 'json'),
            $userUUID
        );

        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::INFO, MonologChannelEnum::EVENT_STORE));
    }
}

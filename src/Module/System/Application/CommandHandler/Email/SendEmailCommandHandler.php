<?php

declare(strict_types=1);

namespace App\Module\System\Application\CommandHandler\Email;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\Company\Domain\Interface\Email\EmailSenderInterface;
use App\Module\System\Application\Command\Email\SendEmailCommand;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Interface\Email\EmailReaderInterface;
use App\Module\System\Domain\Interface\Email\EmailWriterInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendEmailCommandHandler
{
    public function __construct(
        private EmailWriterInterface $emailWriterRepository,
        private EmailReaderInterface $emailReaderRepository,
        private EmailSenderInterface $emailSender,
        private MessageService $messageService,
        #[Autowire(service: 'event.bus')] private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(SendEmailCommand $command): void
    {
        $email = $this->emailReaderRepository->getEmailByUUID($command->emailUUID->toString());

        if (null === $email) {
            $message = $this->messageService->get('email.uuid.notFound', [':uuid' => $command->emailUUID->toString()], 'emails');
            $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, MonologChannelEnum::EVENT_LOG));

            return;
        }

        try {
            $this->emailSender->send($email, $email->getTemplateName());
            $email->markAsSent();
            $message = $this->messageService->get('email.send.success', [':uuid' => $command->emailUUID->toString()], 'emails');
            $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::INFO, MonologChannelEnum::EVENT_LOG));
        } catch (\Throwable $error) {
            $email->markAsFailed($error->getMessage());
            $message = $this->messageService->get('email.send.failed', [':uuid' => $command->emailUUID->toString(), ':errorMessage' => $error->getMessage()], 'emails');
            $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, MonologChannelEnum::EVENT_LOG));
        }

        $this->emailWriterRepository->saveEmailInDB($email);
    }
}

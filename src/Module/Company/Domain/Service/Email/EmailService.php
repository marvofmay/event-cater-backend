<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\Email;

use App\Module\System\Application\Command\Email\SendEmailCommand;
use App\Module\System\Domain\Entity\Email;
use App\Module\System\Domain\Interface\Email\EmailWriterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment as Twig;

final readonly class EmailService
{
    public function __construct(
        private EmailWriterInterface $emailWriterRepository,
        private Twig $twig,
        #[Autowire(service: 'command.bus')] private MessageBusInterface $commandBus,
        private string $projectDir
    ) {
    }

    public function sendEmail(
        array $recipients,
        string $subject,
        ?UserInterface $sender = null,
        string $message = '',
        ?string $templateName = null,
        array $attachments = [],
        array $context = [],
    ): Email {

        $templateBody = null;
        if (null !== $templateName) {
            $logoPath = $this->projectDir . '/public/assets/images/event-cater-logo.png';
            if (file_exists($logoPath)) {
                $base64Logo = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                $context['logoCid'] = $base64Logo;
            }
            $templateBody = $this->twig->render($templateName, $context);
        }

        $email = Email::create(
            subject: $subject,
            recipients: $recipients,
            sender: $sender,
            message: $message,
            templateName: $templateName,
            templateBody: $templateBody,
        );

        if (null !== $templateBody) {
            $email->setRenderedTemplate($templateBody);
            $email->setContext($context);
        }

        foreach ($attachments as $file) {
            $email->addAttachment($file);
        }

        $this->emailWriterRepository->saveEmailInDB($email);

        $this->commandBus->dispatch(new SendEmailCommand($email->getUUID()), [new DispatchAfterCurrentBusStamp()]);

        return $email;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Email;

use App\Module\Company\Domain\Interface\Email\EmailSenderInterface;
use App\Module\System\Domain\Entity\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Part\DataPart;

final readonly class SymfonyEmailSender implements EmailSenderInterface
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(Email $email, ?string $template = null, array $context = []): void
    {
        $message = new SymfonyEmail()->subject($email->getSubject());

        if ($email->getSender()?->getEmail() !== null) {
            $message->from(new Address($email->getSender()->getEmail()));
        } else {
            $message->from(new Address('noreply@hrapp.com', 'System HRApp'));
        }

        foreach ($email->getRecipients() as $recipient) {
            $message->addTo($recipient);
        }

        if ($template !== null) {
            $html = $email->getRenderedTemplate();
            $message->html($html);
        } else {
            $message->html($email->getMessage());
        }


        foreach ($email->getAttachments() as $attachment) {
            $message->addPart(DataPart::fromPath(
                $attachment->getPath(),
                $attachment->getOriginalName()
            ));
        }

        $this->mailer->send($message);
    }
}

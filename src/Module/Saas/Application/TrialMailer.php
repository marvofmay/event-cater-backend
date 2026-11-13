<?php

declare(strict_types=1);

namespace App\Module\Saas\Application;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class TrialMailer
{
    public function __construct(
        #[Autowire(service: 'app.password_recovery.mailer')]
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(default:password_reset_sender:PASSWORD_RESET_SENDER)%')]
        private string $sender,
        #[Autowire('%env(default:trial_notify_email:TRIAL_NOTIFY_EMAIL)%')]
        private string $notifyEmail,
        #[Autowire('%env(default:app_public_url:APP_URL)%')]
        private string $appUrl,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
    ) {
    }

    public function sendOwnerCredentials(string $to, string $companyName, string $slug, #[\SensitiveParameter] string $password, \DateTimeInterface $trialEndsAt): void
    {
        $loginUrl = rtrim($this->appUrl, '/').'/login?tenant='.rawurlencode($slug);
        $ends = $trialEndsAt->format('Y-m-d H:i');
        $body = "Witaj,\n\nTwoje konto próbne EventCater jest gotowe.\n\n"
            ."Firma: {$companyName}\n"
            ."Kod firmy: {$slug}\n"
            ."E-mail (login): {$to}\n"
            ."Hasło: {$password}\n"
            ."Koniec okresu próbnego: {$ends}\n\n"
            ."Logowanie: {$loginUrl}\n\n"
            ."Zachowaj tę wiadomość - hasło nie będzie ponownie wysłane w tej formie.\n"
            ."Po 30 dniach dostęp zostanie zawieszony. Dane zostaną usunięte 7 dni po zakończeniu triala,\n"
            ."jeśli abonament nie zostanie opłacony.\n\n"
            ."EventCater\n";
        $this->send($to, 'EventCater - dane logowania do triala (30 dni)', $body);
    }

    public function sendOperatorNotice(string $companyName, string $slug, string $ownerEmail, \DateTimeInterface $trialEndsAt): void
    {
        $ends = $trialEndsAt->format('Y-m-d H:i');
        $body = "Nowy trial EventCater\n\n"
            ."Firma: {$companyName}\n"
            ."Kod: {$slug}\n"
            ."E-mail właściciela: {$ownerEmail}\n"
            ."Koniec triala: {$ends}\n";
        $this->send($this->notifyEmail, 'EventCater - nowy trial: '.$companyName, $body);
    }

    public function sendReminder(string $to, string $companyName, string $slug, \DateTimeInterface $trialEndsAt, string $windowLabel): void
    {
        $ends = $trialEndsAt->format('Y-m-d H:i');
        $loginUrl = rtrim($this->appUrl, '/').'/login?tenant='.rawurlencode($slug);
        $body = "Witaj,\n\nPrzypominamy, że okres próbny EventCater dla firmy „{$companyName}” "
            ."kończy się za {$windowLabel} (deadline: {$ends}).\n\n"
            ."Kod firmy: {$slug}\n"
            ."Logowanie: {$loginUrl}\n\n"
            ."Po zakończeniu triala konto zostanie zawieszone. Dane pozostaną przez 7 dni - "
            ."potem zostaną usunięte, jeśli abonament nie zostanie opłacony.\n"
            ."Opłata 99 zł brutto (BLIK, Tpay): ".rtrim($this->appUrl, '/')."/oplata\n\n"
            ."Kontakt: {$this->notifyEmail}\n\nEventCater\n";
        $this->send($to, 'EventCater - koniec triala za '.$windowLabel, $body);
    }

    public function sendSuspendedNotice(string $to, string $companyName, string $slug): void
    {
        $body = "Witaj,\n\nOkres próbny EventCater dla firmy „{$companyName}” (kod: {$slug}) zakończył się. "
            ."Konto zostało zawieszone. Dane firmy pozostaną przez 7 dni, potem zostaną usunięte.\n\n"
            ."Aby wznowić dostęp przed usunięciem, opłać abonament: ".rtrim($this->appUrl, '/')."/oplata\n"
            ."albo napisz na {$this->notifyEmail}.\n\nEventCater\n";
        $this->send($to, 'EventCater - koniec triala, konto zawieszone', $body);
    }

    public function sendPaymentReceived(string $to, string $companyName, string $slug, \DateTimeInterface $paidUntil, bool $sandbox): void
    {
        $until = $paidUntil->format('Y-m-d H:i');
        $loginUrl = rtrim($this->appUrl, '/').'/login?tenant='.rawurlencode($slug);
        $mode = $sandbox ? "To była płatność testowa Tpay (sandbox).\n\n" : '';
        $body = "Witaj,\n\nWpłata 99 zł brutto za EventCater dla firmy „{$companyName}” została zaksięgowana.\n\n"
            ."Kod firmy: {$slug}\n"
            ."Abonament aktywny do: {$until}\n"
            ."Logowanie: {$loginUrl}\n\n"
            .$mode
            ."Kolejny miesiąc przedłużysz tą samą płatnością BLIK. Trzy dni po terminie wyślemy przypomnienie. "
            ."Brak wpłaty przez 14 dni zawiesza konto, dane zostają.\n\n"
            ."EventCater\n";
        $this->send($to, 'EventCater - abonament opłacony', $body);
    }

    public function sendPaidReminder(string $to, string $companyName, string $slug, \DateTimeInterface $paidUntil): void
    {
        $due = $paidUntil->format('Y-m-d H:i');
        $payUrl = rtrim($this->appUrl, '/').'/oplata';
        $body = "Witaj,\n\nMinęły 3 dni od terminu opłacenia abonamentu EventCater dla firmy „{$companyName}” "
            ."(kod: {$slug}, termin: {$due}).\n\n"
            ."Prosimy o opłacenie kolejnego miesiąca: 99 zł brutto, BLIK.\n"
            ."{$payUrl}\n\n"
            ."Konto pozostaje aktywne. Jeśli wpłata nie wpłynie w ciągu 14 dni od terminu, dostęp zostanie zawieszony. "
            ."Dane firmy zostaną zachowane.\n\nEventCater\n";
        $this->send($to, 'EventCater - prośba o opłacenie abonamentu', $body);
    }

    public function sendPaidSuspendedNotice(string $to, string $companyName, string $slug): void
    {
        $payUrl = rtrim($this->appUrl, '/').'/oplata';
        $body = "Witaj,\n\nMinęło 14 dni od terminu opłacenia abonamentu EventCater dla firmy „{$companyName}” (kod: {$slug}). "
            ."Nie odnotowaliśmy wpłaty, dlatego konto zostało zawieszone. Dane zostają na koncie.\n\n"
            ."Aby wznowić dostęp, opłać abonament: {$payUrl}\n\nEventCater\n";
        $this->send($to, 'EventCater - minęło 14 dni, konto zawieszone', $body);
    }

    private function send(string $to, string $subject, string $body): void
    {
        $this->assertDeliverableTransport();
        try {
            $this->mailer->send((new Email())->from($this->sender)->to($to)->subject($subject)->text($body));
        } catch (\Throwable $error) {
            $this->logger->error('Trial mail failed.', [
                'to' => $to,
                'subject' => $subject,
                'errorType' => $error::class,
                'errorMessage' => $error->getMessage(),
            ]);
            throw $error;
        }
    }

    private function assertDeliverableTransport(): void
    {
        $host = parse_url($this->mailerDsn, PHP_URL_HOST);
        if (!is_string($host) || in_array(strtolower($host), ['127.0.0.1', 'localhost', 'mailpit', ''], true)) {
            throw new \RuntimeException('MAILER_DSN nie wysyła poczty na zewnątrz (host: '.($host ?: 'brak').'). Skrzynka domeny jest na home.pl. Ustaw w .env MAILER_DSN=smtp://LOGIN:HASLO@smtp.home.pl:587 i PASSWORD_RESET_SENDER na istniejący adres tej skrzynki, potem zrestartuj API.');
        }
    }
}

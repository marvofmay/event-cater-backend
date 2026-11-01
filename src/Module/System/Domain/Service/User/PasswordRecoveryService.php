<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\User;

use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Service\User\LoginPhone;
use App\Module\Saas\Infrastructure\TenantContext;
use App\Module\System\Application\Command\Auth\RequestPasswordResetCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordRecoveryService
{
    public const INVALID_LINK = 'Link jest nieprawidłowy, wygasł lub został już wykorzystany. Poproś o nowy link.';

    public function __construct(
        private Connection $connection,
        private TenantContext $tenant,
        #[Autowire(service: 'app.password_recovery.mailer')] private MailerInterface $mailer,
        private UserPasswordHasherInterface $hasher,
        #[Autowire('%env(default:password_reset_url:PASSWORD_RESET_URL)%')] private string $resetUrl,
        #[Autowire('%env(default:password_reset_sender:PASSWORD_RESET_SENDER)%')] private string $sender = 'noreply@hrapp.com',
    ) {}

    public static function normalizeLogin(string $login): string
    {
        $login = strtolower(trim($login));

        return str_contains($login, '@') ? $login : LoginPhone::normalize($login);
    }

    public function issue(RequestPasswordResetCommand $command): void
    {
        if ($command->requestedAt < time() - 300 || $command->requestedAt > time() + 30) return;
        $url = parse_url($this->resetUrl);
        if (!is_array($url) || !isset($url['host']) || isset($url['fragment']) || isset($url['query']) || isset($url['user']) || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || ($url['scheme'] === 'http' && !in_array($url['host'], ['localhost', '127.0.0.1'], true))) {
            throw new \LogicException('Skonfiguruj bezpieczny PASSWORD_RESET_URL.');
        }
        $this->connection->transactional(function () use ($command): void {
            $column = str_contains($command->login, '@') ? 'email' : 'login_phone';
            $user = $this->connection->fetchAssociative("SELECT u.* FROM user u JOIN employee e ON e.uuid = u.employee_uuid WHERE u.$column = ? AND u.deleted_at IS NULL AND e.deleted_at IS NULL AND e.active = 1 AND u.saas_administrator_uuid IS NULL FOR UPDATE", [$command->login]);
            if (!$user || !$user['email'] || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) return;
            if ($this->connection->fetchOne('SELECT token_hash FROM password_reset_request WHERE request_uuid = ?', [$command->requestUuid])) return;
            if ($this->connection->fetchOne('SELECT token_hash FROM password_reset_request WHERE user_uuid = ? AND created_at > ?', [$user['uuid'], gmdate('Y-m-d H:i:s', time() - 60)])) return;
            $token = bin2hex(random_bytes(32));
            $this->connection->executeStatement('DELETE FROM password_reset_request WHERE user_uuid = ?', [$user['uuid']]);
            $this->connection->insert('password_reset_request', [
                'token_hash' => $this->hashToken($token), 'request_uuid' => $command->requestUuid,
                'user_uuid' => $user['uuid'], 'email' => $user['email'], 'credential_version' => $user['credential_version'],
                'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 900),
            ]);
            $tenant = $this->tenant->current();
            $link = $this->resetUrl.'#'.http_build_query(['token' => $token, 'tenant' => $tenant['slug']]);
            $this->mailer->send((new Email())->from($this->sender)->to($user['email'])
                ->subject('Ustaw nowe hasło — '.$tenant['name'])
                ->text("Poproszono o ustawienie nowego hasła do Twojego konta w firmie ".$tenant['name'].".\n\nOtwórz link (ważny 15 minut, jednorazowy):\n".$link."\n\nDziała również, jeśli masz jeszcze hasło startowe. Jeżeli nie wysyłano tej prośby, zignoruj tę wiadomość. Hasło nie zostało zmienione. Nie przekazuj tego linku innym osobom."));
        });
    }

    public function confirm(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $confirmation): array
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $token)) throw new UnprocessableEntityHttpException(self::INVALID_LINK);
        $hash = $this->hashToken($token);
        $userUuid = $this->connection->fetchOne('SELECT user_uuid FROM password_reset_request WHERE token_hash = ?', [$hash]);
        if (!$userUuid) throw new UnprocessableEntityHttpException(self::INVALID_LINK);

        return $this->connection->transactional(function () use ($hash, $userUuid, $password, $confirmation): array {
            $user = $this->connection->fetchAssociative('SELECT u.* FROM user u JOIN employee e ON e.uuid = u.employee_uuid WHERE u.uuid = ? AND u.deleted_at IS NULL AND e.deleted_at IS NULL AND e.active = 1 AND u.saas_administrator_uuid IS NULL FOR UPDATE', [$userUuid]);
            $reset = $this->connection->fetchAssociative('SELECT * FROM password_reset_request WHERE token_hash = ? FOR UPDATE', [$hash]);
            if (!$user || !$reset || $reset['expires_at'] <= gmdate('Y-m-d H:i:s') || (int) $reset['credential_version'] !== (int) $user['credential_version'] || $reset['email'] !== $user['email']) {
                throw new UnprocessableEntityHttpException(self::INVALID_LINK);
            }
            $errors = PasswordPolicy::errors($password, $confirmation);
            $identity = User::create($user['email']);
            $identity->setPassword($user['password']);
            if ($this->hasher->isPasswordValid($identity, $password)) $errors[] = 'Nowe hasło musi być inne niż dotychczasowe.';
            if ($errors !== []) throw new UnprocessableEntityHttpException(implode(' ', $errors));
            $passwordHash = $this->hasher->hashPassword($identity, $password);
            $this->connection->executeStatement('UPDATE user SET password = ?, must_change_password = 0, credential_version = credential_version + 1 WHERE uuid = ?', [$passwordHash, $userUuid]);
            $this->connection->executeStatement('DELETE FROM password_reset_request WHERE user_uuid = ?', [$userUuid]);

            return ['userUuid' => $userUuid, 'email' => $user['email']];
        });
    }

    public function notify(string $userUuid, string $email): void
    {
        if (!$this->connection->fetchOne('SELECT uuid FROM user WHERE uuid = ? AND email = ? AND deleted_at IS NULL', [$userUuid, $email])) return;
        $this->mailer->send((new Email())->from($this->sender)->to($email)->subject('Hasło zostało zmienione')
            ->text('Hasło do Twojego konta w firmie '.$this->tenant->current()['name'].' zostało zmienione. Starsze sesje zostały unieważnione. Jeśli nie zmieniano hasła, niezwłocznie skontaktuj się z administratorem firmy.'));
    }

    private function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $this->tenant->current()['uuid'].'|'.$token);
    }
}

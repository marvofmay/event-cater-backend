<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final readonly class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);
        if ($envelope->last(ReceivedStamp::class)) {
            if (!$stamp) {
                throw new UnrecoverableMessageHandlingException('Missing tenant context. Legacy queued messages require manual review.');
            }
            try {
                $this->context->select($stamp->slug);
            } catch (\Throwable $error) {
                throw new UnrecoverableMessageHandlingException('Tenant unavailable.', 0, $error);
            }
            if ((int) $this->context->current()['version'] !== $stamp->version) {
                throw new UnrecoverableMessageHandlingException('Tenant access changed since dispatch.');
            }
        } elseif (!$stamp) {
            $tenant = $this->context->current();
            $envelope = $envelope->with(new TenantStamp($tenant['slug'], (int) $tenant['version']));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}

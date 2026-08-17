<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Http;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'kernel.exception', priority: 300)]
final readonly class UnifiedApiExceptionListener
{
    public function __construct(
        private TranslatorInterface $translator,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof HandlerFailedException && $exception->getPrevious()) {
            $exception = $exception->getPrevious();
        }

        $channel = $this->getControllerErrorChannel($event);
        if ($exception instanceof UnprocessableEntityHttpException) {
            $validationException = $event->getThrowable()->getPrevious();
            $errorMessages = [];
            foreach ($validationException->getViolations() as $violation) {
                $errorMessages[$this->translator->trans($violation->getPropertyPath(), [], 'messages')] = $violation->getMessage();
            }

            $errorString = implode('; ', array_map(
                fn ($field, $message) => "$field: $message",
                array_keys($errorMessages),
                $errorMessages
            ));

            $this->logError($channel, 'Validation errors: ' . $errorString);

            $event->setResponse(new JsonResponse([
                'message' => $this->translator->trans('errors.occurred', [], 'validators'),
                'errors' => $errorMessages,
            ]));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $message = match (true) {
                $exception instanceof NotFoundHttpException => $this->translator->trans('notFound'),
                $exception instanceof MethodNotAllowedHttpException =>
                $this->translator->trans('methodNotAllowed'),

                default => $exception->getMessage() ?: $this->translator->trans('errors.http'),
            };

            $this->logError($channel, $message);

            $event->setResponse(new JsonResponse(['message' => $message], $exception->getStatusCode()));

            return;
        }


        $message = sprintf('%s', $exception->getMessage());
        $this->logError($channel, $message);
        $event->setResponse(new JsonResponse(['message' => $message], $exception->getCode()));
    }

    private function getControllerErrorChannel(ExceptionEvent $event): MonologChannelEnum
    {
        $controller = $event->getRequest()->attributes->get('_controller');
        if (!$controller || !is_string($controller)) {
            return MonologChannelEnum::MAIN;
        }

        $controllerClass = explode('::', $controller)[0];
        if (!class_exists($controllerClass)) {
            return MonologChannelEnum::MAIN;
        }

        $reflection = new ReflectionClass($controllerClass);
        $attribute = $reflection->getAttributes(ErrorChannel::class)[0] ?? null;
        if ($attribute === null) {
            return MonologChannelEnum::MAIN;
        }
        $instance = $attribute->newInstance();

        return $instance->channel;
    }

    private function logError(MonologChannelEnum $channel, string $message): void
    {
        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, $channel));
    }
}

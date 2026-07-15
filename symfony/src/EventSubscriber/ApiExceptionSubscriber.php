<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\BookingConflictException;
use App\Exception\InvalidPaymentMethodException;
use App\Exception\PaymentAlreadyPaidException;
use App\Exception\PaymentCancelledException;
use App\Exception\SeatOutOfRangeException;
use App\Exception\TripNotAvailableException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Intercepte toutes les exceptions sur les routes /api/* et retourne
 * une réponse JSON uniforme au lieu du HTML par défaut de Symfony.
 *
 * Format de réponse :
 *   { "erreur": "Message lisible", "code": 404 }
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // N'intercepte que les routes /api/*
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        [$message, $status] = $this->resolve($exception);

        $event->setResponse(new JsonResponse(
            ['message' => $message, 'code' => $status],
            $status,
        ));
    }

    /**
     * @return array{string, int}
     */
    private function resolve(\Throwable $e): array
    {
        return match (true) {
            // Exceptions métier
            $e instanceof BookingConflictException    => [$e->getMessage(), Response::HTTP_CONFLICT],
            $e instanceof TripNotAvailableException   => [$e->getMessage(), Response::HTTP_BAD_REQUEST],
            $e instanceof SeatOutOfRangeException     => [$e->getMessage(), Response::HTTP_BAD_REQUEST],
            $e instanceof InvalidPaymentMethodException => [$e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY],
            $e instanceof PaymentAlreadyPaidException => [$e->getMessage(), Response::HTTP_BAD_REQUEST],
            $e instanceof PaymentCancelledException   => [$e->getMessage(), Response::HTTP_BAD_REQUEST],
            // Exceptions HTTP Symfony
            $e instanceof NotFoundHttpException      => ['Ressource introuvable.', Response::HTTP_NOT_FOUND],
            $e instanceof AccessDeniedHttpException  => ['Accès refusé.', Response::HTTP_FORBIDDEN],
            $e instanceof HttpExceptionInterface     => [$e->getMessage(), $e->getStatusCode()],
            // Erreur inattendue
            default => ['Une erreur interne est survenue.', Response::HTTP_INTERNAL_SERVER_ERROR],
        };
    }
}

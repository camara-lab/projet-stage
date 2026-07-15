<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Applique le rate limiting sur les endpoints d'authentification.
 *
 * Limites :
 *   POST /api/auth/login    → 5 tentatives / minute / IP
 *   POST /api/auth/register → 3 inscriptions / heure / IP
 *   POST /api/auth/refresh  → 30 renouvellements / heure / IP
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class RateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiLoginLimiter,
        private readonly RateLimiterFactory $apiRegisterLimiter,
        private readonly RateLimiterFactory $apiRefreshLimiter,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();
        $method  = $request->getMethod();
        $ip      = $request->getClientIp() ?? '0.0.0.0';

        if ($method !== 'POST') {
            return;
        }

        $limiter = match (true) {
            $path === '/api/auth/login'    => $this->apiLoginLimiter->create($ip),
            $path === '/api/auth/register' => $this->apiRegisterLimiter->create($ip),
            $path === '/api/auth/refresh'  => $this->apiRefreshLimiter->create($ip),
            default                        => null,
        };

        if ($limiter === null) {
            return;
        }

        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $event->setResponse(new JsonResponse(
                [
                    'error'       => 'Trop de tentatives. Veuillez réessayer plus tard.',
                    'retry_after' => $retryAfter,
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter],
            ));
        }
    }
}

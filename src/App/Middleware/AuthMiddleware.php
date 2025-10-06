<?php

declare(strict_types=1);

namespace FP\DMS\App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private array $publicRoutes = [
        '/login',
        '/api/v1/tick',
    ];

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $path = $request->getUri()->getPath();

        // Allow public routes
        foreach ($this->publicRoutes as $publicRoute) {
            if (str_starts_with($path, $publicRoute)) {
                return $handler->handle($request);
            }
        }

        // Check if user is authenticated
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}

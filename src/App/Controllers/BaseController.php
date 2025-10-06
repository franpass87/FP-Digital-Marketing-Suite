<?php

declare(strict_types=1);

namespace FP\DMS\App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class BaseController
{
    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function redirect(Response $response, string $url, int $status = 302): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus($status);
    }

    protected function render(Response $response, string $template, array $data = []): Response
    {
        // TODO: Implement template rendering (Twig, Plates, or similar)
        $html = $this->renderTemplate($template, $data);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html');
    }

    private function renderTemplate(string $template, array $data): string
    {
        // Simple template rendering - replace with proper template engine
        $content = "<html><body><h1>{$template}</h1><pre>" . print_r($data, true) . "</pre></body></html>";

        return $content;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DomainsRepository;
use App\Service\PortChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/api/domains')]
final class DomainsApiController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'domains-api';

    public function __construct(
        private readonly DomainsRepository $domainsRepository,
        private readonly PortChecker $portChecker,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'api_domains_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->serializeAll());
    }

    #[Route('', name: 'api_domains_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($error = $this->checkCsrf($request)) {
            return $error;
        }

        $data = $this->decodeJsonBody($request);
        if ($data === null) {
            return $this->error('Request body must be valid JSON.');
        }

        $domain = $this->validateDomain($data['domain'] ?? null);
        if ($domain === null) {
            return $this->error('Domain must be a valid hostname (e.g. sub.example.com).');
        }

        $port = $this->validatePort($data['port'] ?? null);
        if ($port === null) {
            return $this->error('Port must be an integer between 1 and 65535.');
        }

        $comment = $this->validateComment($data['comment'] ?? '');
        if ($comment === null) {
            return $this->error('Comment is too long.');
        }

        if (\array_key_exists($domain, $this->domainsRepository->all())) {
            return $this->error(\sprintf('Domain "%s" already exists.', $domain), JsonResponse::HTTP_CONFLICT);
        }

        $this->domainsRepository->add($domain, $port, $comment);

        return new JsonResponse($this->serializeAll(), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{domain}', name: 'api_domains_update', methods: ['PUT'])]
    public function update(string $domain, Request $request): JsonResponse
    {
        if ($error = $this->checkCsrf($request)) {
            return $error;
        }

        if (!\array_key_exists($domain, $this->domainsRepository->all())) {
            return $this->error(\sprintf('Domain "%s" does not exist.', $domain), JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJsonBody($request);
        if ($data === null) {
            return $this->error('Request body must be valid JSON.');
        }

        $newDomain = $this->validateDomain($data['domain'] ?? $domain);
        if ($newDomain === null) {
            return $this->error('Domain must be a valid hostname (e.g. sub.example.com or localhost).');
        }

        $port = $this->validatePort($data['port'] ?? null);
        if ($port === null) {
            return $this->error('Port must be an integer between 1 and 65535.');
        }

        $comment = $this->validateComment($data['comment'] ?? '');
        if ($comment === null) {
            return $this->error('Comment is too long.');
        }

        if ($newDomain !== $domain && \array_key_exists($newDomain, $this->domainsRepository->all())) {
            return $this->error(\sprintf('Domain "%s" already exists.', $newDomain), JsonResponse::HTTP_CONFLICT);
        }

        $this->domainsRepository->update($domain, $newDomain, $port, $comment);

        return new JsonResponse($this->serializeAll());
    }

    #[Route('/{domain}', name: 'api_domains_delete', methods: ['DELETE'])]
    public function delete(string $domain, Request $request): JsonResponse
    {
        if ($error = $this->checkCsrf($request)) {
            return $error;
        }

        if (!\array_key_exists($domain, $this->domainsRepository->all())) {
            return $this->error(\sprintf('Domain "%s" does not exist.', $domain), JsonResponse::HTTP_NOT_FOUND);
        }

        $this->domainsRepository->remove($domain);

        return new JsonResponse($this->serializeAll());
    }

    private function checkCsrf(Request $request): ?JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            return $this->error('Invalid CSRF token.', JsonResponse::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function validateDomain(mixed $domain): ?string
    {
        if (!\is_string($domain)) {
            return null;
        }

        $domain = trim($domain);
        if ($domain === '' || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i', $domain)) {
            return null;
        }

        return $domain;
    }

    private function validatePort(mixed $port): ?int
    {
        if (!\is_int($port) && !(\is_string($port) && ctype_digit($port))) {
            return null;
        }

        $port = (int) $port;

        return ($port >= 1 && $port <= 65535) ? $port : null;
    }

    private function validateComment(mixed $comment): ?string
    {
        if (!\is_string($comment)) {
            return null;
        }

        $comment = trim($comment);

        return \strlen($comment) <= 500 ? $comment : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonBody(Request $request): ?array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            return null;
        }
    }

    private function error(string $message, int $status = JsonResponse::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    /**
     * @return list<array{domain: string, port: int, comment: string, online: bool}>
     */
    private function serializeAll(): array
    {
        $result = [];
        foreach ($this->domainsRepository->all() as $domain => $entry) {
            $result[] = [
                'domain' => $domain,
                'port' => $entry['port'],
                'comment' => $entry['comment'],
                'online' => $this->portChecker->isOnline($entry['port']),
            ];
        }

        return $result;
    }
}

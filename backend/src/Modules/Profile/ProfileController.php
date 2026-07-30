<?php

declare(strict_types=1);

namespace App\Modules\Profile;

use App\Core\Http\JsonErrorResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class ProfileController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly ProfileService $profileService)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/me',
        summary: "Current user's profile",
        tags: ['Profile'],
        responses: [new OA\Response(response: 200, description: 'Profile')]
    )]
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(200, $this->profileService->show($this->userId($request)))
        );
    }

    #[OA\Put(
        path: '/api/v1/me',
        summary: "Update the current user's name",
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['name'], properties: [
                new OA\Property(property: 'name', type: 'string'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated profile'),
            new OA\Response(response: 422, description: 'Name is required'),
        ]
    )]
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(
                200,
                $this->profileService->update($this->userId($request), $this->body($request))
            )
        );
    }

    #[OA\Post(
        path: '/api/v1/me/avatar',
        summary: 'Upload/replace the current user\'s avatar (multipart/form-data, field "avatar")',
        tags: ['Profile'],
        responses: [
            new OA\Response(response: 200, description: 'Updated profile with the new avatar URL'),
            new OA\Response(response: 422, description: 'Invalid file (size, type, or corrupt image)'),
        ]
    )]
    public function uploadAvatar(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $file = $request->getUploadedFiles()['avatar'] ?? null;

            if (!$file instanceof UploadedFileInterface) {
                throw new ProfileException('No avatar file was uploaded.');
            }

            return $this->json(200, $this->profileService->uploadAvatar($this->userId($request), $file));
        });
    }

    #[OA\Delete(
        path: '/api/v1/me/avatar',
        summary: "Remove the current user's avatar",
        tags: ['Profile'],
        responses: [new OA\Response(response: 200, description: 'Updated profile, avatarUrl now null')]
    )]
    public function deleteAvatar(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(200, $this->profileService->deleteAvatar($this->userId($request)))
        );
    }

    #[OA\Put(
        path: '/api/v1/me/password',
        summary: "Change the current user's password",
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['current_password', 'new_password'], properties: [
                new OA\Property(property: 'current_password', type: 'string'),
                new OA\Property(property: 'new_password', type: 'string'),
            ])
        ),
        responses: [
            new OA\Response(response: 204, description: 'Password changed'),
            new OA\Response(response: 422, description: 'Current password incorrect, or new password too short'),
        ]
    )]
    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $this->profileService->changePassword($this->userId($request), $this->body($request));

            return $this->psr17Factory->createResponse(204);
        });
    }

    private function userId(ServerRequestInterface $request): int
    {
        $claims = $request->getAttribute('authClaims');
        $userId = is_array($claims) ? ($claims['sub'] ?? null) : null;

        if ($userId === null) {
            throw new ProfileException('Missing user context.', 401);
        }

        return (int) $userId;
    }

    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (ProfileException $e) {
            return JsonErrorResponse::build($e->status, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function json(int $status, mixed $payload): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response;
    }
}

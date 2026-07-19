<?php

declare(strict_types=1);

namespace App\Http;

use App\Identity\AuthenticatedSession;
use App\Identity\BearerToken;
use App\Identity\EmailUnavailable;
use App\Identity\IdentityService;
use App\Identity\InvalidCredentials;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

final readonly class AuthenticationHttpHandler
{
    public function __construct(
        private IdentityService $identity,
    ) {}

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = $this->jsonObject($request, ['email', 'displayName', 'password']);
        } catch (UnsupportedMediaType) {
            return JsonResponse::error(415, 'identity.unsupported_media_type');
        } catch (InvalidIdentityRequest) {
            return JsonResponse::error(400, 'identity.invalid_request');
        }

        $violations = $this->registrationViolations($input);
        if ($violations !== []) {
            return $this->validationFailure($violations);
        }

        try {
            return $this->sessionResponse(201, $this->identity->register(
                $input['email'],
                $input['displayName'],
                $input['password'],
            ));
        } catch (EmailUnavailable) {
            return JsonResponse::error(409, 'identity.email_unavailable');
        }
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $input = $this->jsonObject($request, ['email', 'password']);
        } catch (UnsupportedMediaType) {
            return JsonResponse::error(415, 'identity.unsupported_media_type');
        } catch (InvalidIdentityRequest) {
            return JsonResponse::error(400, 'identity.invalid_request');
        }

        $bearer = BearerToken::fromRequest($request);
        if ($bearer->present && (!$bearer->valid || $bearer->rawToken === null)) {
            return JsonResponse::error(400, 'identity.invalid_request');
        }

        $violations = $this->loginViolations($input);
        if ($violations !== []) {
            return $this->validationFailure($violations);
        }

        try {
            return $this->sessionResponse(200, $this->identity->login(
                $input['email'],
                $input['password'],
                $bearer->rawToken,
            ));
        } catch (InvalidCredentials) {
            return JsonResponse::error(401, 'authentication.invalid_credentials');
        }
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $bearer = BearerToken::fromRequest($request);
        if ($bearer->present && !$bearer->valid) {
            return JsonResponse::error(400, 'identity.invalid_request');
        }

        $this->identity->logout($bearer->rawToken);

        return JsonResponse::create(204);
    }

    /**
     * @param list<string> $knownFields
     * @return array<string, mixed>
     */
    private function jsonObject(ServerRequestInterface $request, array $knownFields): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (preg_match('/\\Aapplication\\/json(?:\\s*;.*)?\\z/iD', $contentType) !== 1) {
            throw new UnsupportedMediaType();
        }

        $body = (string) $request->getBody();
        if (strlen($body) > 16_384) {
            throw new InvalidIdentityRequest();
        }

        try {
            $decoded = json_decode($body, associative: false, depth: 32, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidIdentityRequest();
        }

        if (!$decoded instanceof stdClass) {
            throw new InvalidIdentityRequest();
        }

        $input = get_object_vars($decoded);
        foreach (array_keys($input) as $field) {
            if (!in_array($field, $knownFields, strict: true)) {
                throw new InvalidIdentityRequest();
            }
        }

        return $input;
    }

    /** @param array<string, mixed> $input @return list<array{field: string, code: string}> */
    private function registrationViolations(array $input): array
    {
        $violations = $this->emailViolations($input);
        $displayName = $input['displayName'] ?? null;
        if (!is_string($displayName) || trim($displayName) === '') {
            $violations[] = ['field' => 'displayName', 'code' => 'identity.display_name.required'];
        } elseif ($this->characterLength(trim($displayName)) > 80) {
            $violations[] = ['field' => 'displayName', 'code' => 'identity.display_name.too_long'];
        }

        return array_merge($violations, $this->passwordViolations($input));
    }

    /** @param array<string, mixed> $input @return list<array{field: string, code: string}> */
    private function loginViolations(array $input): array
    {
        return array_merge($this->emailViolations($input), $this->passwordViolations($input));
    }

    /** @param array<string, mixed> $input @return list<array{field: string, code: string}> */
    private function emailViolations(array $input): array
    {
        $email = $input['email'] ?? null;
        if (
            !is_string($email)
            || strlen(trim($email)) > 254
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
        ) {
            return [['field' => 'email', 'code' => 'identity.email.invalid']];
        }

        return [];
    }

    /** @param array<string, mixed> $input @return list<array{field: string, code: string}> */
    private function passwordViolations(array $input): array
    {
        $password = $input['password'] ?? null;
        if (!is_string($password)) {
            return [['field' => 'password', 'code' => 'identity.password.invalid']];
        }

        $length = $this->characterLength($password);
        if ($length < 12) {
            return [['field' => 'password', 'code' => 'identity.password.too_short']];
        }

        if ($length > 128) {
            return [['field' => 'password', 'code' => 'identity.password.too_long']];
        }

        return [];
    }

    private function characterLength(string $value): int
    {
        $count = preg_match_all('/./us', $value);

        return is_int($count) ? $count : strlen($value);
    }

    /** @param list<array{field: string, code: string}> $violations */
    private function validationFailure(array $violations): ResponseInterface
    {
        return JsonResponse::error(422, 'identity.validation_failed', ['violations' => $violations]);
    }

    private function sessionResponse(int $status, AuthenticatedSession $session): ResponseInterface
    {
        return JsonResponse::create($status, [
            'user' => [
                'id' => $session->user->id,
                'email' => $session->user->email,
                'displayName' => $session->user->displayName,
            ],
            'sessionToken' => $session->rawToken,
        ]);
    }
}

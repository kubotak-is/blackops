<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\FrontendContractManifestArtifact;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use BlackOps\Internal\Frontend\FrontendValueFieldContract;
use InvalidArgumentException;
use JsonException;

/**
 * Source rendering validates every contract boundary before emitting executable modules.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:no-else-clause
 */
final readonly class FrontendTypeScriptGenerator
{
    public function __construct(
        private FrontendContractHasher $hasher = new FrontendContractHasher(),
    ) {}

    public function generate(FrontendContractManifestArtifact $artifact): FrontendGeneratedTree
    {
        $operations = $artifact->manifest->operations;
        usort($operations, static fn(FrontendOperationContract $left, FrontendOperationContract $right): int => strcmp(
            $left->module,
            $right->module,
        ));

        $hash = $this->hasher->hash($artifact->manifest);
        $marker = new FrontendGenerationMarker($artifact->applicationBuildId, $hash);
        $files = [
            'client.ts' => $this->clientSource(),
            'manifest.json' => $marker->encode(),
            'types.ts' => $this->typesSource(),
        ];

        foreach ($operations as $operation) {
            $this->assertOperation($operation);
            if (array_key_exists($operation->module, $files)) {
                throw new InvalidArgumentException('Frontend operation module path is duplicated.');
            }
            $files[$operation->module] = $this->operationSource($operation);
        }

        return new FrontendGeneratedTree($files);
    }

    private function typesSource(): string
    {
        return <<<'TYPES'
            export type OperationCredentials = 'omit' | 'same-origin' | 'include';

            export type OperationAbortSignal = Readonly<{
              aborted: boolean;
              reason?: unknown;
            }>;

            export type OperationRequestOptions = Readonly<{
              baseUrl?: string;
              headers?: Readonly<Record<string, string>>;
              credentials?: OperationCredentials;
              signal?: OperationAbortSignal;
            }>;

            export type OperationRequest = Readonly<{
              url: string;
              method: string;
              headers: Readonly<Record<string, string>>;
              body?: string;
              credentials?: OperationCredentials;
              signal?: OperationAbortSignal;
            }>;
            TYPES . "\n";
    }

    private function clientSource(): string
    {
        return <<<'CLIENT'
            import type { OperationRequest, OperationRequestOptions } from './types';

            export type OperationScalarKind = 'string' | 'integer' | 'float' | 'boolean';
            export type OperationBindingSource = 'path' | 'query' | 'header' | 'body';

            export type OperationBinding = Readonly<{
              name: string;
              transportName: string;
              type: OperationScalarKind;
              source: OperationBindingSource;
              required: boolean;
              nullable: boolean;
            }>;

            type OperationInput = Readonly<Record<string, unknown>>;

            export function buildOperationUrl(
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              parameters: OperationInput,
            ): string {
              let path = pathTemplate;
              const query: string[] = [];

              for (const binding of bindings) {
                if (binding.source !== 'path' && binding.source !== 'query') {
                  continue;
                }

                const value = readValue(parameters, binding);
                if (value === undefined) {
                  if (binding.source === 'path') {
                    throw new Error(`Operation path field "${binding.name}" is required.`);
                  }
                  continue;
                }
                if (value === null) {
                  if (!binding.nullable || binding.source === 'path') {
                    throw new Error(`Operation field "${binding.name}" has an invalid value.`);
                  }
                  continue;
                }

                const encoded = encodeURIComponent(encodeWireScalar(value, binding));
                if (binding.source === 'path') {
                  const placeholder = new RegExp(`\\{${binding.transportName}(?::[^}]*)?\\}`, 'g');
                  path = path.replace(placeholder, encoded);
                } else {
                  query.push(`${encodeURIComponent(binding.transportName)}=${encoded}`);
                }
              }

              if (/\{[A-Za-z_][A-Za-z0-9_]*(?::[^}]*)?\}/.test(path)) {
                throw new Error('Operation path contains an unresolved parameter.');
              }

              return query.length === 0 ? path : `${path}?${query.join('&')}`;
            }

            export function buildOperationRequest(
              method: string,
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              value: OperationInput,
              options: OperationRequestOptions = {},
            ): OperationRequest {
              const urlBindings = bindings.filter(
                (binding): boolean => binding.source === 'path' || binding.source === 'query',
              );
              const headers: Record<string, string> = {};
              const protectedHeaders = new Set<string>(['content-type']);
              const body: Record<string, string | number | boolean | null> = {};
              const hasBody = bindings.some((binding): boolean => binding.source === 'body');

              for (const binding of bindings) {
                if (binding.source === 'header') {
                  assertHeader(binding.transportName, '');
                  protectedHeaders.add(binding.transportName.toLowerCase());
                }
              }

              for (const binding of bindings) {
                const fieldValue = readValue(value, binding);

                if (binding.source === 'header') {
                  if (fieldValue === undefined) {
                    continue;
                  }
                  if (fieldValue === null && binding.nullable) {
                    continue;
                  }
                  const headerValue = encodeWireScalar(fieldValue, binding);
                  assertHeader(binding.transportName, headerValue);
                  headers[binding.transportName] = headerValue;
                  continue;
                }

                if (binding.source === 'body') {
                  if (fieldValue === undefined) {
                    continue;
                  }
                  body[binding.transportName] = validateScalar(fieldValue, binding);
                }
              }

              if (hasBody) {
                headers['Content-Type'] = 'application/json';
              }

              for (const [name, headerValue] of Object.entries(options.headers ?? {})) {
                assertHeader(name, headerValue);
                if (!protectedHeaders.has(name.toLowerCase())) {
                  headers[name] = headerValue;
                }
              }

              const relativeUrl = buildOperationUrl(pathTemplate, urlBindings, value);
              const request: {
                url: string;
                method: string;
                headers: Readonly<Record<string, string>>;
                body?: string;
                credentials?: OperationRequest['credentials'];
                signal?: OperationRequest['signal'];
              } = {
                url: joinBaseUrl(options.baseUrl, relativeUrl),
                method,
                headers: Object.freeze(headers),
              };

              if (hasBody) {
                try {
                  request.body = JSON.stringify(body);
                } catch {
                  throw new Error('Operation body could not be serialized.');
                }
              }
              if (options.credentials !== undefined) {
                request.credentials = options.credentials;
              }
              if (options.signal !== undefined) {
                request.signal = options.signal;
              }

              return Object.freeze(request);
            }

            function readValue(input: OperationInput, binding: OperationBinding): unknown {
              const value = input[binding.name];
              if (value === undefined && binding.required) {
                throw new Error(`Operation field "${binding.name}" is required.`);
              }

              return value;
            }

            function validateScalar(
              value: unknown,
              binding: OperationBinding,
            ): string | number | boolean | null {
              if (value === null) {
                if (binding.nullable) {
                  return null;
                }
                throw new Error(`Operation field "${binding.name}" has an invalid value.`);
              }

              switch (binding.type) {
                case 'string':
                  if (typeof value === 'string') return value;
                  break;
                case 'integer':
                  if (typeof value === 'number' && Number.isSafeInteger(value)) return value;
                  break;
                case 'float':
                  if (typeof value === 'number' && Number.isFinite(value)) return value;
                  break;
                case 'boolean':
                  if (typeof value === 'boolean') return value;
                  break;
              }

              throw new Error(`Operation field "${binding.name}" has an invalid value.`);
            }

            function encodeWireScalar(value: unknown, binding: OperationBinding): string {
              const scalar = validateScalar(value, binding);
              if (scalar === null) {
                throw new Error(`Operation field "${binding.name}" has an invalid value.`);
              }
              if (typeof scalar === 'boolean') {
                return scalar ? 'true' : 'false';
              }

              return String(scalar);
            }

            function assertHeader(name: string, value: string): void {
              if (name === '' || /[\r\n]/.test(name) || /[\r\n]/.test(value)) {
                throw new Error('Operation request header is invalid.');
              }
            }

            function joinBaseUrl(baseUrl: string | undefined, relativeUrl: string): string {
              if (baseUrl === undefined) {
                return relativeUrl;
              }
              const match = /^(https?):\/\/([^/?#]+)(\/[^?#]*)?$/i.exec(baseUrl);
              if (match === null || !isValidAuthority(match[2]) || /\s/.test(match[3] ?? '') || /%(?![0-9a-f]{2})/i.test(match[3] ?? '')) {
                throw new Error('Operation base URL is invalid.');
              }

              return `${baseUrl.replace(/\/+$/, '')}/${relativeUrl.replace(/^\/+/, '')}`;
            }

            function isValidAuthority(authority: string): boolean {
              if (authority === '' || authority.includes('@') || /[\s\\]/.test(authority)) {
                return false;
              }

              if (authority.startsWith('[')) {
                const match = /^\[([0-9a-f:]+)\](?::([0-9]+))?$/i.exec(authority);
                return match !== null && isValidIpv6(match[1]) && isValidPort(match[2]);
              }

              const match = /^([^:]+)(?::([0-9]+))?$/.exec(authority);
              if (match === null || !isValidPort(match[2])) {
                return false;
              }
              const labels = match[1].split('.');
              return labels.every((label): boolean => /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i.test(label));
            }

            function isValidPort(port: string | undefined): boolean {
              if (port === undefined) {
                return true;
              }
              const number = Number(port);
              return Number.isInteger(number) && number >= 0 && number <= 65535;
            }

            function isValidIpv6(address: string): boolean {
              if ((address.match(/::/g) ?? []).length > 1 || address.includes(':::')) {
                return false;
              }
              const groups = address.split(':').filter((group): boolean => group !== '');
              if (!groups.every((group): boolean => /^[0-9a-f]{1,4}$/i.test(group))) {
                return false;
              }

              return address.includes('::') ? groups.length < 8 : groups.length === 8;
            }
            CLIENT . "\n";
    }

    private function operationSource(FrontendOperationContract $operation): string
    {
        $valueName = $operation->exportName . 'Value';
        $urlName = $operation->exportName . 'UrlParameters';
        $urlFields = array_values(array_filter(
            $operation->value->fields,
            static fn(FrontendValueFieldContract $field): bool => in_array(
                $field->source,
                ['path', 'query'],
                strict: true,
            ),
        ));
        $import = str_repeat('../', substr_count(haystack: dirname($operation->module), needle: '/') + 1);
        /** @var list<string> $lines */
        $lines = [
            sprintf("import { buildOperationRequest, buildOperationUrl } from '%sclient';", $import),
            sprintf("import type { OperationRequest, OperationRequestOptions } from '%stypes';", $import),
            '',
        ];
        foreach ($this->typeDeclaration($valueName, $operation->value->fields) as $line) {
            $lines[] = $line;
        }
        if ($urlFields !== []) {
            $lines[] = '';
            foreach ($this->typeDeclaration($urlName, $urlFields) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'const bindings = Object.freeze([';
        foreach ($operation->value->fields as $field) {
            $lines[] =
                '  Object.freeze('
                . $this->json([
                    'name' => $field->name,
                    'transportName' => $field->transportName,
                    'type' => $field->type,
                    'source' => $field->source,
                    'required' => $field->required,
                    'nullable' => $field->nullable,
                ])
                . '),';
        }
        $lines[] = '] as const);';
        $lines[] = '';
        $lines[] = sprintf('export const %s = Object.freeze({', $operation->exportName);
        $lines[] = sprintf('  type: %s as const,', $this->json($operation->typeId));
        $lines[] = sprintf('  method: %s as const,', $this->json($operation->method));
        $lines[] = sprintf('  path: %s as const,', $this->json($operation->path));
        $lines[] = sprintf('  strategy: %s as const,', $this->json($operation->strategy));
        if ($urlFields === []) {
            $lines[] = '  url(): string {';
            $lines[] = sprintf('    return buildOperationUrl(%s, bindings, {});', $this->json($operation->path));
        } else {
            $lines[] = sprintf('  url(parameters: %s): string {', $urlName);
            $lines[] = sprintf(
                '    return buildOperationUrl(%s, bindings, parameters);',
                $this->json($operation->path),
            );
        }
        $lines[] = '  },';
        $lines[] = sprintf('  toRequest(value: %s, options?: OperationRequestOptions): OperationRequest {', $valueName);
        $lines[] = sprintf(
            '    return buildOperationRequest(%s, %s, bindings, value, options);',
            $this->json($operation->method),
            $this->json($operation->path),
        );
        $lines[] = '  },';
        $lines[] = '});';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<FrontendValueFieldContract> $fields
     * @return list<string>
     */
    private function typeDeclaration(string $name, array $fields): array
    {
        if ($fields === []) {
            return [sprintf('export type %s = Readonly<Record<never, never>>;', $name)];
        }

        $lines = [sprintf('export type %s = Readonly<{', $name)];
        foreach ($fields as $field) {
            $property = $this->propertyName($field->name) . ($field->required ? '' : '?');
            $type = match ($field->type) {
                'string' => 'string',
                'integer', 'float' => 'number',
                'boolean' => 'boolean',
                default => throw new InvalidArgumentException('Frontend scalar kind is invalid.'),
            };
            $lines[] = sprintf('  readonly %s: %s;', $property, $type . ($field->nullable ? ' | null' : ''));
        }
        $lines[] = '}>;';

        return $lines;
    }

    private function assertOperation(FrontendOperationContract $operation): void
    {
        if (
            preg_match('/^operations\/(?:[a-z0-9-]+\/)*[a-z0-9-]+\.ts$/D', $operation->module) !== 1
            || preg_match('/^[A-Z][A-Za-z0-9]*$/D', $operation->exportName) !== 1
            || !str_starts_with($operation->path, '/')
            || str_contains($operation->path, "\r")
            || str_contains($operation->path, "\n")
        ) {
            throw new InvalidArgumentException('Frontend operation generation metadata is invalid.');
        }
        foreach ($operation->value->fields as $field) {
            if (str_contains($field->transportName, "\r") || str_contains($field->transportName, "\n")) {
                throw new InvalidArgumentException('Frontend operation transport name is invalid.');
            }
            if ($field->source === 'path' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $field->transportName) !== 1) {
                throw new InvalidArgumentException('Frontend operation path transport name is invalid.');
            }
            if (in_array($operation->method, ['GET', 'HEAD'], strict: true) && $field->source === 'body') {
                throw new InvalidArgumentException('Frontend operation does not permit a body for GET or HEAD.');
            }
        }

        $matches = [];
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]*)?\}/', $operation->path, $matches);
        $placeholders = $matches[1] ?? [];
        $pathBindings = array_map(
            static fn(FrontendValueFieldContract $field): string => $field->transportName,
            array_values(array_filter(
                $operation->value->fields,
                static fn(FrontendValueFieldContract $field): bool => $field->source === 'path',
            )),
        );
        sort($placeholders);
        sort($pathBindings);
        if (
            $placeholders !== $pathBindings
            || count($pathBindings) !== count(array_unique($pathBindings))
            || array_any(
                $operation->value->fields,
                static fn(FrontendValueFieldContract $field): bool => (
                    $field->source === 'path'
                    && (!$field->required || $field->nullable)
                ),
            )
        ) {
            throw new InvalidArgumentException('Frontend operation path bindings are invalid.');
        }
    }

    private function propertyName(string $name): string
    {
        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/D', $name) === 1) {
            return $name;
        }

        return $this->json($name);
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new InvalidArgumentException('Frontend generation metadata cannot be encoded.');
        }
    }
}

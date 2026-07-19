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

            export type OperationFetchHeaders = Readonly<{
              get(name: string): string | null;
            }>;

            export type OperationFetchResponse = Readonly<{
              status: number;
              headers: OperationFetchHeaders;
              text(): Promise<string>;
            }>;

            export type OperationFetchRequest = Readonly<{
              method: string;
              headers: Readonly<Record<string, string>>;
              body?: string;
              credentials?: OperationCredentials;
              signal?: OperationAbortSignal;
            }>;

            export type OperationFetch = (
              url: string,
              request: OperationFetchRequest,
            ) => Promise<OperationFetchResponse>;

            export type OperationCallOptions = OperationRequestOptions & Readonly<{
              fetch?: OperationFetch;
            }>;

            export type OperationWaitSignal = Readonly<{
              aborted: boolean;
              reason?: unknown;
              addEventListener(
                type: 'abort',
                listener: () => void,
                options?: Readonly<{ once?: boolean }>,
              ): void;
              removeEventListener(type: 'abort', listener: () => void): void;
            }>;

            export type OperationWaitClock = Readonly<{
              nowMilliseconds(): number;
            }>;

            export type OperationWaitTimer = Readonly<{
              setTimeout(callback: () => void, milliseconds: number): unknown;
              clearTimeout(handle: unknown): void;
            }>;

            export type OperationWaitOptions = Omit<OperationCallOptions, 'signal'> & Readonly<{
              signal: OperationWaitSignal;
              maxWaitMilliseconds: number;
              clock?: OperationWaitClock;
              timer?: OperationWaitTimer;
            }>;

            export type DeferredAcknowledgement = Readonly<{
              operationId: string;
              acceptedAt: string;
            }>;

            export type ProtocolError = Readonly<{
              code: string;
            }>;

            export type OperationRejection = Readonly<{
              category: 'business_rule' | 'unauthorized' | 'forbidden' | 'not_found' | 'conflict';
              code: string;
              operationId?: string;
            }>;

            export type ValidationViolation<TField extends string> = Readonly<{
              field: TField;
              rule: string;
              code: string;
            }>;

            export type ValidationRejection<TField extends string> = Readonly<{
              operationId: string;
              code: 'validation.failed';
              violations: readonly ValidationViolation<TField>[];
            }>;

            export type InternalOperationError = Readonly<{
              code: 'internal_error';
              operationId?: string;
            }>;

            export type OperationTransportError = Readonly<{
              code: 'missing_fetch' | 'invalid_base_url' | 'network_error' | 'aborted' | 'unexpected_response';
            }>;

            export type OperationStatusTransportError = Readonly<{
              code:
                | 'invalid_operation_id'
                | 'missing_fetch'
                | 'invalid_base_url'
                | 'network_error'
                | 'aborted'
                | 'unexpected_response';
            }>;

            export type OperationWaitTransportError = Readonly<{
              code:
                | 'invalid_operation_id'
                | 'missing_fetch'
                | 'invalid_base_url'
                | 'network_error'
                | 'aborted'
                | 'invalid_wait_options'
                | 'poll_timeout'
                | 'unexpected_response';
            }>;

            export type OperationAcceptedStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'accepted';
            }>;

            export type OperationRunningStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'running';
              attempt: number;
            }>;

            export type OperationRetryScheduledStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'retry_scheduled';
              attempt: number;
              retryAt: string;
            }>;

            export type OperationCompletedStatus<TType extends string, TOutcome> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'completed';
              outcome: TOutcome;
            }>;

            export type OperationRejectedStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'rejected';
              error: Readonly<{ category: string; code: string }>;
            }>;

            export type OperationFailedStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'failed';
              error: Readonly<{ code: 'operation_failed' }>;
            }>;

            export type OperationDeadLetteredStatus<TType extends string> = Readonly<{
              schemaVersion: 1;
              operationId: string;
              operationType: TType;
              state: 'dead_lettered';
              error: Readonly<{ code: 'operation_dead_lettered' }>;
            }>;

            export type OperationStatusResult<TType extends string, TOutcome> =
              | Readonly<{
                  ok: true;
                  kind: 'accepted';
                  status: 200;
                  data: OperationAcceptedStatus<TType>;
                  retryAfterSeconds: number;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'running';
                  status: 200;
                  data: OperationRunningStatus<TType>;
                  retryAfterSeconds: number;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'retry_scheduled';
                  status: 200;
                  data: OperationRetryScheduledStatus<TType>;
                  retryAfterSeconds: number;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'completed';
                  status: 200;
                  data: OperationCompletedStatus<TType, TOutcome>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'rejected';
                  status: 200;
                  data: OperationRejectedStatus<TType>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'failed';
                  status: 200;
                  data: OperationFailedStatus<TType>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'dead_lettered';
                  status: 200;
                  data: OperationDeadLetteredStatus<TType>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'authentication';
                  status: 401;
                  error: Readonly<{ code: string }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'unavailable';
                  status: 404;
                  error: Readonly<{ code: 'operation_unavailable' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'expired';
                  status: 410;
                  error: Readonly<{ code: 'operation_expired' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'internal';
                  status: 500;
                  error: Readonly<{ code: 'internal_error' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'transport';
                  status: null;
                  error: OperationStatusTransportError;
                }>;

            export type OperationWaitResult<TType extends string, TOutcome> =
              | Readonly<{
                  ok: true;
                  kind: 'completed';
                  status: 200;
                  data: OperationCompletedStatus<TType, TOutcome>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'rejected';
                  status: 200;
                  data: OperationRejectedStatus<TType>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'failed';
                  status: 200;
                  data: OperationFailedStatus<TType>;
                }>
              | Readonly<{
                  ok: true;
                  kind: 'dead_lettered';
                  status: 200;
                  data: OperationDeadLetteredStatus<TType>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'authentication';
                  status: 401;
                  error: Readonly<{ code: string }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'unavailable';
                  status: 404;
                  error: Readonly<{ code: 'operation_unavailable' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'expired';
                  status: 410;
                  error: Readonly<{ code: 'operation_expired' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'internal';
                  status: 500;
                  error: Readonly<{ code: 'internal_error' }>;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'transport';
                  status: null;
                  error: OperationWaitTransportError;
                }>;

            export type OperationFailureResult<TField extends string> =
              | Readonly<{ ok: false; kind: 'protocol'; status: 400; error: ProtocolError }>
              | Readonly<{
                  ok: false;
                  kind: 'rejected';
                  status: 400 | 401 | 403 | 404 | 409;
                  error: OperationRejection;
                }>
              | Readonly<{
                  ok: false;
                  kind: 'validation';
                  status: 422;
                  error: ValidationRejection<TField>;
                }>
              | Readonly<{ ok: false; kind: 'internal'; status: 500; error: InternalOperationError }>
              | Readonly<{
                  ok: false;
                  kind: 'transport';
                  status: null;
                  error: OperationTransportError;
                }>;

            export type InlineOutcomeOperationResult<TOutcome, TField extends string> =
              | Readonly<{ ok: true; kind: 'completed'; status: 200; data: TOutcome }>
              | OperationFailureResult<TField>;

            export type InlineVoidOperationResult<TField extends string> =
              | Readonly<{ ok: true; kind: 'completed'; status: 204; data: undefined }>
              | OperationFailureResult<TField>;

            export type DeferredOperationResult<TField extends string> =
              | Readonly<{ ok: true; kind: 'accepted'; status: 202; data: DeferredAcknowledgement }>
              | OperationFailureResult<TField>;
            TYPES . "\n";
    }

    private function clientSource(): string
    {
        return <<<'CLIENT'
            import type {
              DeferredOperationResult,
              InlineOutcomeOperationResult,
              InlineVoidOperationResult,
              OperationCallOptions,
              OperationFailureResult,
              OperationFetch,
              OperationFetchRequest,
              OperationRejection,
              OperationRequest,
              OperationRequestOptions,
              OperationStatusResult,
              OperationWaitClock,
              OperationWaitOptions,
              OperationWaitSignal,
              OperationWaitTimer,
              OperationWaitResult,
              ValidationRejection,
              ValidationViolation,
            } from './types';

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

            export type OperationOutcomeField = Readonly<{
              name: string;
              type: OperationScalarKind;
              nullable: boolean;
            }>;

            export type InlineOutcomeResponseContract<TField extends string> = Readonly<{
              mode: 'inline_outcome';
              outcomeFields: readonly OperationOutcomeField[];
              validationFields: readonly TField[];
            }>;

            export type InlineVoidResponseContract<TField extends string> = Readonly<{
              mode: 'inline_void';
              validationFields: readonly TField[];
            }>;

            export type DeferredResponseContract<TField extends string> = Readonly<{
              mode: 'deferred';
              validationFields: readonly TField[];
            }>;

            export type OperationStatusResponseContract<TType extends string> = Readonly<{
              operationType: TType;
              outcomeMode: 'outcome' | 'void';
              outcomeFields: readonly OperationOutcomeField[];
            }>;

            type OperationInput = Readonly<Record<string, unknown>>;

            type OperationFetchResponseSnapshot = Readonly<{
              status: number;
              getHeader(name: string): unknown;
              readBody(): Promise<unknown>;
            }>;

            class InvalidOperationBaseUrlError extends Error {}

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
                throw new InvalidOperationBaseUrlError('Operation base URL is invalid.');
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

            export function fetchOperation<TOutcome, TField extends string>(
              method: string,
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              value: OperationInput,
              options: OperationCallOptions | undefined,
              responseContract: InlineOutcomeResponseContract<TField>,
            ): Promise<InlineOutcomeOperationResult<TOutcome, TField>>;

            export function fetchOperation<TOutcome, TField extends string>(
              method: string,
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              value: OperationInput,
              options: OperationCallOptions | undefined,
              responseContract: InlineVoidResponseContract<TField>,
            ): Promise<InlineVoidOperationResult<TField>>;

            export function fetchOperation<TOutcome, TField extends string>(
              method: string,
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              value: OperationInput,
              options: OperationCallOptions | undefined,
              responseContract: DeferredResponseContract<TField>,
            ): Promise<DeferredOperationResult<TField>>;

            export async function fetchOperation<TOutcome, TField extends string>(
              method: string,
              pathTemplate: string,
              bindings: readonly OperationBinding[],
              value: OperationInput,
              options: OperationCallOptions = {},
              responseContract:
                | InlineOutcomeResponseContract<TField>
                | InlineVoidResponseContract<TField>
                | DeferredResponseContract<TField>,
            ): Promise<
              | InlineOutcomeOperationResult<TOutcome, TField>
              | InlineVoidOperationResult<TField>
              | DeferredOperationResult<TField>
            > {
              let request: OperationRequest;
              try {
                request = buildOperationRequest(method, pathTemplate, bindings, value, options);
              } catch (error: unknown) {
                if (error instanceof InvalidOperationBaseUrlError) {
                  return transportResult('invalid_base_url');
                }
                throw error;
              }

              const operationFetch = selectOperationFetch(options.fetch);
              if (operationFetch === undefined) {
                return transportResult('missing_fetch');
              }

              const fetchRequest: {
                method: string;
                headers: Readonly<Record<string, string>>;
                body?: string;
                credentials?: OperationFetchRequest['credentials'];
                signal?: OperationFetchRequest['signal'];
              } = {
                method: request.method,
                headers: request.headers,
              };
              if (request.body !== undefined) {
                fetchRequest.body = request.body;
              }
              if (request.credentials !== undefined) {
                fetchRequest.credentials = request.credentials;
              }
              if (request.signal !== undefined) {
                fetchRequest.signal = request.signal;
              }

              let received: unknown;
              try {
                received = await operationFetch(request.url, Object.freeze(fetchRequest));
              } catch {
                return transportResult(options.signal?.aborted === true ? 'aborted' : 'network_error');
              }

              const response = snapshotOperationFetchResponse(received);
              if (response === undefined) {
                return transportResult('unexpected_response');
              }

              let rawBody: unknown;
              try {
                rawBody = await response.readBody();
              } catch {
                return transportResult('network_error');
              }
              if (typeof rawBody !== 'string') {
                return transportResult('unexpected_response');
              }

              return decodeOperationResponse(response, rawBody, responseContract);
            }

            export async function fetchOperationStatus<TType extends string, TOutcome>(
              operationId: string,
              options: OperationCallOptions = {},
              contract: OperationStatusResponseContract<TType>,
            ): Promise<OperationStatusResult<TType, TOutcome>> {
              if (!isCanonicalOperationId(operationId)) {
                return statusTransportResult('invalid_operation_id');
              }

              let url: string;
              try {
                url = joinBaseUrl(options.baseUrl, `/operations/${operationId}`);
              } catch (error: unknown) {
                if (error instanceof InvalidOperationBaseUrlError) {
                  return statusTransportResult('invalid_base_url');
                }
                throw error;
              }

              const operationFetch = selectOperationFetch(options.fetch);
              if (operationFetch === undefined) {
                return statusTransportResult('missing_fetch');
              }

              const request: {
                method: 'GET';
                headers: Readonly<Record<string, string>>;
                credentials?: OperationFetchRequest['credentials'];
                signal?: OperationFetchRequest['signal'];
              } = {
                method: 'GET',
                headers: statusRequestHeaders(options.headers),
              };
              if (options.credentials !== undefined) {
                request.credentials = options.credentials;
              }
              if (options.signal !== undefined) {
                request.signal = options.signal;
              }

              let received: unknown;
              try {
                received = await operationFetch(url, Object.freeze(request));
              } catch {
                return statusTransportResult(options.signal?.aborted === true ? 'aborted' : 'network_error');
              }

              const response = snapshotOperationFetchResponse(received);
              if (response === undefined) {
                return statusTransportResult('unexpected_response');
              }

              let rawBody: unknown;
              try {
                rawBody = await response.readBody();
              } catch {
                return statusTransportResult(options.signal?.aborted === true ? 'aborted' : 'network_error');
              }
              if (typeof rawBody !== 'string') {
                return statusTransportResult('unexpected_response');
              }

              return decodeOperationStatusResponse<TType, TOutcome>(
                response,
                rawBody,
                operationId,
                contract,
              );
            }

            export async function waitForOperationStatus<TType extends string, TOutcome>(
              operationId: string,
              options: OperationWaitOptions,
              contract: OperationStatusResponseContract<TType>,
            ): Promise<OperationWaitResult<TType, TOutcome>> {
              if (!isCanonicalOperationId(operationId)) {
                return waitTransportResult('invalid_operation_id');
              }

              const wait = resolveWaitInvocation(options);
              if (wait === undefined) {
                return waitTransportResult('invalid_wait_options');
              }
              const initialAbort = readWaitAbort(wait.signal);
              if (initialAbort === undefined) {
                return waitTransportResult('invalid_wait_options');
              }
              if (initialAbort) {
                return waitTransportResult('aborted');
              }

              const startedAt = readWaitClock(wait.clock);
              if (startedAt === undefined || wait.maxWaitMilliseconds > Number.MAX_SAFE_INTEGER - startedAt) {
                return waitTransportResult('invalid_wait_options');
              }
              const deadline = startedAt + wait.maxWaitMilliseconds;
              let previousClock = startedAt;

              while (true) {
                const beforeRequestAbort = readWaitAbort(wait.signal);
                if (beforeRequestAbort === undefined) {
                  return waitTransportResult('invalid_wait_options');
                }
                if (beforeRequestAbort) {
                  return waitTransportResult('aborted');
                }

                const beforeRequest = readWaitClock(wait.clock, previousClock);
                if (beforeRequest === undefined) {
                  return waitTransportResult('invalid_wait_options');
                }
                previousClock = beforeRequest;
                if (beforeRequest >= deadline) {
                  return waitTransportResult('poll_timeout');
                }

                const request = await fetchOperationStatusWithinWait<TType, TOutcome>(
                  operationId,
                  options,
                  contract,
                  wait.signal,
                  wait.timer,
                  deadline - beforeRequest,
                );
                if (request.kind === 'aborted') {
                  return waitTransportResult('aborted');
                }
                if (request.kind === 'poll_timeout') {
                  return waitTransportResult('poll_timeout');
                }
                if (request.kind === 'invalid_wait_options') {
                  return waitTransportResult('invalid_wait_options');
                }
                const result = request.result;
                if (!result.ok) {
                  return result;
                }
                if (!isNonTerminalStatus(result)) {
                  return result;
                }

                const observedAt = readWaitClock(wait.clock, previousClock);
                if (observedAt === undefined) {
                  return waitTransportResult('invalid_wait_options');
                }
                previousClock = observedAt;
                if (observedAt >= deadline) {
                  return waitTransportResult('poll_timeout');
                }

                const remaining = deadline - observedAt;
                const retryMilliseconds = result.retryAfterSeconds > Number.MAX_SAFE_INTEGER / 1000
                  ? Number.MAX_SAFE_INTEGER
                  : result.retryAfterSeconds * 1000;
                const reachesDeadline = retryMilliseconds >= remaining;
                const delay = reachesDeadline ? remaining : retryMilliseconds;
                const sleep = await sleepForOperationWait(wait.signal, wait.timer, delay);
                if (sleep === 'aborted') {
                  return waitTransportResult('aborted');
                }
                if (sleep === 'invalid_wait_options') {
                  return waitTransportResult('invalid_wait_options');
                }

                const afterSleepAbort = readWaitAbort(wait.signal);
                if (afterSleepAbort === undefined) {
                  return waitTransportResult('invalid_wait_options');
                }
                if (afterSleepAbort) {
                  return waitTransportResult('aborted');
                }
                const afterSleep = readWaitClock(wait.clock, previousClock);
                if (afterSleep === undefined) {
                  return waitTransportResult('invalid_wait_options');
                }
                previousClock = afterSleep;
                if (reachesDeadline || afterSleep >= deadline) {
                  return waitTransportResult('poll_timeout');
                }
              }
            }

            type OperationWaitInvocation = Readonly<{
              signal: OperationWaitSignal;
              maxWaitMilliseconds: number;
              clock: OperationWaitClock;
              timer: OperationWaitTimer;
            }>;

            type OperationWaitSleepResult = 'elapsed' | 'aborted' | 'invalid_wait_options';

            type OperationWaitRequestResult<TType extends string, TOutcome> =
              | Readonly<{ kind: 'status'; result: OperationStatusResult<TType, TOutcome> }>
              | Readonly<{ kind: 'aborted' }>
              | Readonly<{ kind: 'poll_timeout' }>
              | Readonly<{ kind: 'invalid_wait_options' }>;

            function resolveWaitInvocation(options: unknown): OperationWaitInvocation | undefined {
              try {
                if (!isRecord(options) || !Number.isSafeInteger(options.maxWaitMilliseconds)
                  || (options.maxWaitMilliseconds as number) <= 0) {
                  return undefined;
                }
                const signal = options.signal;
                if (!isRecord(signal)
                  || typeof signal.addEventListener !== 'function'
                  || typeof signal.removeEventListener !== 'function') {
                  return undefined;
                }
                const clock = resolveWaitClock(options.clock);
                const timer = resolveWaitTimer(options.timer);
                if (clock === undefined || timer === undefined) {
                  return undefined;
                }

                return Object.freeze({
                  signal: signal as OperationWaitSignal,
                  maxWaitMilliseconds: options.maxWaitMilliseconds as number,
                  clock,
                  timer,
                });
              } catch {
                return undefined;
              }
            }

            function resolveWaitClock(value: unknown): OperationWaitClock | undefined {
              if (value !== undefined) {
                return isRecord(value) && typeof value.nowMilliseconds === 'function'
                  ? value as OperationWaitClock
                  : undefined;
              }

              try {
                const now = Date.now;
                return typeof now === 'function'
                  ? Object.freeze({ nowMilliseconds: (): number => now.call(Date) })
                  : undefined;
              } catch {
                return undefined;
              }
            }

            function resolveWaitTimer(value: unknown): OperationWaitTimer | undefined {
              if (value !== undefined) {
                return isRecord(value)
                  && typeof value.setTimeout === 'function'
                  && typeof value.clearTimeout === 'function'
                  ? value as OperationWaitTimer
                  : undefined;
              }

              try {
                const runtime = globalThis as unknown as Readonly<{
                  setTimeout?: unknown;
                  clearTimeout?: unknown;
                }>;
                const schedule = runtime.setTimeout;
                const cancel = runtime.clearTimeout;
                if (typeof schedule !== 'function' || typeof cancel !== 'function') {
                  return undefined;
                }

                return Object.freeze({
                  setTimeout: (callback: () => void, milliseconds: number): unknown => (
                    schedule.call(runtime, callback, milliseconds)
                  ),
                  clearTimeout: (handle: unknown): void => {
                    cancel.call(runtime, handle);
                  },
                });
              } catch {
                return undefined;
              }
            }

            function readWaitAbort(signal: OperationWaitSignal): boolean | undefined {
              try {
                return typeof signal.aborted === 'boolean' ? signal.aborted : undefined;
              } catch {
                return undefined;
              }
            }

            function readWaitClock(clock: OperationWaitClock, previous?: number): number | undefined {
              try {
                const value = clock.nowMilliseconds();
                return Number.isSafeInteger(value)
                  && value >= 0
                  && (previous === undefined || value >= previous)
                  ? value
                  : undefined;
              } catch {
                return undefined;
              }
            }

            function isNonTerminalStatus<TType extends string, TOutcome>(
              result: OperationStatusResult<TType, TOutcome>,
            ): result is Extract<OperationStatusResult<TType, TOutcome>, Readonly<{
              ok: true;
              kind: 'accepted' | 'running' | 'retry_scheduled';
            }>> {
              return result.ok
                && (result.kind === 'accepted' || result.kind === 'running' || result.kind === 'retry_scheduled');
            }

            function fetchOperationStatusWithinWait<TType extends string, TOutcome>(
              operationId: string,
              options: OperationWaitOptions,
              contract: OperationStatusResponseContract<TType>,
              signal: OperationWaitSignal,
              timer: OperationWaitTimer,
              remainingMilliseconds: number,
            ): Promise<OperationWaitRequestResult<TType, TOutcome>> {
              return new Promise((resolve): void => {
                let settled = false;
                let listenerMayBeRegistered = false;
                let timerRegistered = false;
                let timerFiredBeforeRegistration = false;
                let timerRegistrationInProgress = false;
                let abortDuringTimerRegistration = false;
                let timerHandle: unknown;

                const finish = (
                  result: OperationWaitRequestResult<TType, TOutcome>,
                ): void => {
                  if (settled) {
                    return;
                  }
                  settled = true;
                  let cleanupFailed = false;
                  if (timerRegistered) {
                    try {
                      timer.clearTimeout(timerHandle);
                    } catch {
                      cleanupFailed = true;
                    }
                  }
                  if (listenerMayBeRegistered) {
                    try {
                      signal.removeEventListener('abort', onAbort);
                    } catch {
                      cleanupFailed = true;
                    }
                  }
                  resolve(cleanupFailed && result.kind !== 'aborted'
                    ? Object.freeze({ kind: 'invalid_wait_options' })
                    : result);
                };
                const onAbort = (): void => {
                  if (timerRegistrationInProgress) {
                    abortDuringTimerRegistration = true;
                    return;
                  }
                  finish(Object.freeze({ kind: 'aborted' }));
                };

                listenerMayBeRegistered = true;
                try {
                  signal.addEventListener('abort', onAbort, Object.freeze({ once: true }));
                } catch {
                  finish(Object.freeze({ kind: 'invalid_wait_options' }));
                  return;
                }
                if (settled) {
                  return;
                }
                const afterRegistration = readWaitAbort(signal);
                if (afterRegistration === undefined) {
                  finish(Object.freeze({ kind: 'invalid_wait_options' }));
                  return;
                }
                if (afterRegistration) {
                  finish(Object.freeze({ kind: 'aborted' }));
                  return;
                }

                fetchOperationStatus<TType, TOutcome>(operationId, options, contract).then(
                  (result): void => {
                    const afterRequestAbort = readWaitAbort(signal);
                    if (afterRequestAbort === undefined) {
                      finish(Object.freeze({ kind: 'invalid_wait_options' }));
                    } else if (afterRequestAbort) {
                      finish(Object.freeze({ kind: 'aborted' }));
                    } else {
                      finish(Object.freeze({ kind: 'status', result }));
                    }
                  },
                  (): void => {
                    finish(Object.freeze({
                      kind: 'status',
                      result: statusTransportResult('unexpected_response'),
                    }));
                  },
                );

                armWaitRequestDeadline();

                function armWaitRequestDeadline(): void {
                  if (settled) {
                    return;
                  }
                  const beforeTimer = readWaitAbort(signal);
                  if (beforeTimer === undefined) {
                    finish(Object.freeze({ kind: 'invalid_wait_options' }));
                    return;
                  }
                  if (beforeTimer) {
                    finish(Object.freeze({ kind: 'aborted' }));
                    return;
                  }

                  timerRegistrationInProgress = true;
                  try {
                    timerHandle = timer.setTimeout((): void => {
                      if (!timerRegistered) {
                        timerFiredBeforeRegistration = true;
                        return;
                      }
                      const atDeadline = readWaitAbort(signal);
                      finish(
                        Object.freeze({
                          kind: atDeadline === true ? 'aborted' : atDeadline === false
                            ? 'poll_timeout'
                            : 'invalid_wait_options',
                        }),
                      );
                    }, remainingMilliseconds);
                    timerRegistered = true;
                  } catch {
                    timerRegistrationInProgress = false;
                    finish(Object.freeze({ kind: abortDuringTimerRegistration
                      ? 'aborted'
                      : 'invalid_wait_options' }));
                    return;
                  }
                  timerRegistrationInProgress = false;
                  if (abortDuringTimerRegistration) {
                    finish(Object.freeze({ kind: 'aborted' }));
                    return;
                  }
                  if (timerFiredBeforeRegistration) {
                    const atDeadline = readWaitAbort(signal);
                    finish(
                      Object.freeze({
                        kind: atDeadline === true ? 'aborted' : atDeadline === false
                          ? 'poll_timeout'
                          : 'invalid_wait_options',
                      }),
                    );
                    return;
                  }
                  if (settled) {
                    return;
                  }

                  const afterTimer = readWaitAbort(signal);
                  if (afterTimer === undefined) {
                    finish(Object.freeze({ kind: 'invalid_wait_options' }));
                  } else if (afterTimer) {
                    finish(Object.freeze({ kind: 'aborted' }));
                  }
                }
              });
            }

            function sleepForOperationWait(
              signal: OperationWaitSignal,
              timer: OperationWaitTimer,
              milliseconds: number,
            ): Promise<OperationWaitSleepResult> {
              const beforeRegistration = readWaitAbort(signal);
              if (beforeRegistration === undefined) {
                return Promise.resolve('invalid_wait_options');
              }
              if (beforeRegistration) {
                return Promise.resolve('aborted');
              }

              return new Promise((resolve): void => {
                let settled = false;
                let listenerMayBeRegistered = false;
                let timerRegistered = false;
                let timerFiredBeforeRegistration = false;
                let timerRegistrationInProgress = false;
                let abortDuringTimerRegistration = false;
                let timerHandle: unknown;

                const finish = (result: OperationWaitSleepResult): void => {
                  if (settled) {
                    return;
                  }
                  settled = true;
                  let cleanupFailed = false;
                  if (timerRegistered) {
                    try {
                      timer.clearTimeout(timerHandle);
                    } catch {
                      cleanupFailed = true;
                    }
                  }
                  if (listenerMayBeRegistered) {
                    try {
                      signal.removeEventListener('abort', onAbort);
                    } catch {
                      cleanupFailed = true;
                    }
                  }
                  resolve(cleanupFailed && result !== 'aborted' ? 'invalid_wait_options' : result);
                };
                const onAbort = (): void => {
                  if (timerRegistrationInProgress) {
                    abortDuringTimerRegistration = true;
                    return;
                  }
                  finish('aborted');
                };

                listenerMayBeRegistered = true;
                try {
                  signal.addEventListener('abort', onAbort, Object.freeze({ once: true }));
                } catch {
                  finish('invalid_wait_options');
                  return;
                }
                if (settled) {
                  return;
                }

                const afterRegistration = readWaitAbort(signal);
                if (afterRegistration === undefined) {
                  finish('invalid_wait_options');
                  return;
                }
                if (afterRegistration) {
                  finish('aborted');
                  return;
                }

                timerRegistrationInProgress = true;
                try {
                  timerHandle = timer.setTimeout((): void => {
                    if (!timerRegistered) {
                      timerFiredBeforeRegistration = true;
                      return;
                    }
                    finish('elapsed');
                  }, milliseconds);
                  timerRegistered = true;
                } catch {
                  timerRegistrationInProgress = false;
                  finish(abortDuringTimerRegistration ? 'aborted' : 'invalid_wait_options');
                  return;
                }
                timerRegistrationInProgress = false;
                if (abortDuringTimerRegistration) {
                  finish('aborted');
                  return;
                }
                if (timerFiredBeforeRegistration) {
                  finish('elapsed');
                  return;
                }

                const afterTimer = readWaitAbort(signal);
                if (afterTimer === undefined) {
                  finish('invalid_wait_options');
                } else if (afterTimer) {
                  finish('aborted');
                }
              });
            }

            function statusRequestHeaders(
              configured: Readonly<Record<string, string>> | undefined,
            ): Readonly<Record<string, string>> {
              const headers: Record<string, string> = {};
              const names = new Set<string>();
              for (const [name, value] of Object.entries(configured ?? {})) {
                assertHeader(name, value);
                const normalized = name.toLowerCase();
                if (normalized === 'content-type' || names.has(normalized)) {
                  continue;
                }
                names.add(normalized);
                headers[name] = value;
              }

              return Object.freeze(headers);
            }

            function selectOperationFetch(injected: OperationFetch | undefined): OperationFetch | undefined {
              if (injected !== undefined) {
                return typeof injected === 'function' ? injected : undefined;
              }

              try {
                const runtime = globalThis as unknown as { fetch?: unknown };
                const runtimeFetch = runtime.fetch;
                return typeof runtimeFetch === 'function'
                  ? (runtimeFetch as OperationFetch).bind(runtime)
                  : undefined;
              } catch {
                return undefined;
              }
            }

            function snapshotOperationFetchResponse(
              value: unknown,
            ): OperationFetchResponseSnapshot | undefined {
              try {
                if (!isRecord(value)) {
                  return undefined;
                }
                const status = value.status;
                const headers = value.headers;
                const readHeader = isRecord(headers) ? headers.get : undefined;
                const readBody = value.text;
                if (!Number.isInteger(status) || typeof readHeader !== 'function' || typeof readBody !== 'function') {
                  return undefined;
                }

                return Object.freeze({
                  status: status as number,
                  getHeader: (name: string): unknown => readHeader.call(headers, name),
                  readBody: async (): Promise<unknown> => readBody.call(value),
                });
              } catch {
                return undefined;
              }
            }

            function decodeOperationResponse<TOutcome, TField extends string>(
              response: OperationFetchResponseSnapshot,
              rawBody: string,
              contract:
                | InlineOutcomeResponseContract<TField>
                | InlineVoidResponseContract<TField>
                | DeferredResponseContract<TField>,
            ):
              | InlineOutcomeOperationResult<TOutcome, TField>
              | InlineVoidOperationResult<TField>
              | DeferredOperationResult<TField> {
              if (response.status === 204) {
                return contract.mode === 'inline_void' && rawBody === ''
                  ? Object.freeze({ ok: true, kind: 'completed', status: 204, data: undefined })
                  : transportResult('unexpected_response');
              }

              let contentType: unknown;
              try {
                contentType = response.getHeader('content-type');
              } catch {
                return transportResult('unexpected_response');
              }
              if (!isJsonMediaType(contentType)) {
                return transportResult('unexpected_response');
              }

              let payload: unknown;
              try {
                payload = JSON.parse(rawBody);
              } catch {
                return transportResult('unexpected_response');
              }
              if (!isRecord(payload)) {
                return transportResult('unexpected_response');
              }

              if (response.status === 200 && contract.mode === 'inline_outcome') {
                const outcome = decodeOutcome<TOutcome>(payload, contract.outcomeFields);
                return outcome === undefined
                  ? transportResult('unexpected_response')
                  : Object.freeze({ ok: true, kind: 'completed', status: 200, data: outcome });
              }
              if (response.status === 202 && contract.mode === 'deferred') {
                return decodeAcknowledgement(payload);
              }
              if (response.status === 400 && payload.status === 'error') {
                return decodeProtocolError(payload);
              }
              if ([400, 401, 403, 404, 409].includes(response.status)) {
                return decodeRejection(payload, response.status);
              }
              if (response.status === 422) {
                return decodeValidation(payload, contract.validationFields);
              }
              if (response.status === 500) {
                return decodeInternalError(payload);
              }

              return transportResult('unexpected_response');
            }

            function decodeOperationStatusResponse<TType extends string, TOutcome>(
              response: OperationFetchResponseSnapshot,
              rawBody: string,
              requestedOperationId: string,
              contract: OperationStatusResponseContract<TType>,
            ): OperationStatusResult<TType, TOutcome> {
              let contentType: unknown;
              try {
                contentType = response.getHeader('content-type');
              } catch {
                return statusTransportResult('unexpected_response');
              }
              if (!isJsonMediaType(contentType)) {
                return statusTransportResult('unexpected_response');
              }

              let payload: unknown;
              try {
                payload = JSON.parse(rawBody);
              } catch {
                return statusTransportResult('unexpected_response');
              }
              if (!isRecord(payload)) {
                return statusTransportResult('unexpected_response');
              }

              if (response.status === 401) {
                if (
                  !hasExactKeys(payload, ['status', 'category', 'code'])
                  || payload.status !== 'error'
                  || payload.category !== 'unauthorized'
                  || !isNonEmptyString(payload.code)
                ) {
                  return statusTransportResult('unexpected_response');
                }
                return Object.freeze({
                  ok: false,
                  kind: 'authentication',
                  status: 401,
                  error: Object.freeze({ code: payload.code }),
                });
              }
              if (response.status === 404) {
                return decodeExactStatusError<TType, TOutcome>(
                  payload,
                  404,
                  'unavailable',
                  'operation_unavailable',
                );
              }
              if (response.status === 410) {
                return decodeExactStatusError<TType, TOutcome>(
                  payload,
                  410,
                  'expired',
                  'operation_expired',
                );
              }
              if (response.status === 500) {
                return decodeExactStatusError<TType, TOutcome>(payload, 500, 'internal', 'internal_error');
              }
              if (
                response.status !== 200
                || payload.schemaVersion !== 1
                || payload.operationId !== requestedOperationId
                || payload.operationType !== contract.operationType
                || !isNonEmptyString(payload.state)
              ) {
                return statusTransportResult('unexpected_response');
              }

              const common = {
                schemaVersion: 1 as const,
                operationId: requestedOperationId,
                operationType: contract.operationType,
              };

              switch (payload.state) {
                case 'accepted': {
                  if (!hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state'])) {
                    return statusTransportResult('unexpected_response');
                  }
                  const retryAfterSeconds = decodeRetryAfterSeconds(response);
                  if (retryAfterSeconds === undefined) {
                    return statusTransportResult('unexpected_response');
                  }
                  const data = Object.freeze({ ...common, state: 'accepted' as const });
                  return Object.freeze({ ok: true, kind: 'accepted', status: 200, data, retryAfterSeconds });
                }
                case 'running': {
                  if (
                    !hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state', 'attempt'])
                    || !isPositiveSafeInteger(payload.attempt)
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  const retryAfterSeconds = decodeRetryAfterSeconds(response);
                  if (retryAfterSeconds === undefined) {
                    return statusTransportResult('unexpected_response');
                  }
                  const data = Object.freeze({ ...common, state: 'running' as const, attempt: payload.attempt });
                  return Object.freeze({ ok: true, kind: 'running', status: 200, data, retryAfterSeconds });
                }
                case 'retry_scheduled': {
                  if (
                    !hasExactKeys(
                      payload,
                      ['schemaVersion', 'operationId', 'operationType', 'state', 'attempt', 'retryAt'],
                    )
                    || !isPositiveSafeInteger(payload.attempt)
                    || !isUtcMicrosecondTimestamp(payload.retryAt)
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  const retryAfterSeconds = decodeRetryAfterSeconds(response);
                  if (retryAfterSeconds === undefined) {
                    return statusTransportResult('unexpected_response');
                  }
                  const data = Object.freeze({
                    ...common,
                    state: 'retry_scheduled' as const,
                    attempt: payload.attempt,
                    retryAt: payload.retryAt,
                  });
                  return Object.freeze({
                    ok: true,
                    kind: 'retry_scheduled',
                    status: 200,
                    data,
                    retryAfterSeconds,
                  });
                }
                case 'completed': {
                  if (
                    !hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state', 'outcome'])
                    || !hasNoRetryAfter(response)
                    || !isRecord(payload.outcome)
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  let outcome: TOutcome;
                  if (contract.outcomeMode === 'void') {
                    if (!hasExactKeys(payload.outcome, [])) {
                      return statusTransportResult('unexpected_response');
                    }
                    outcome = undefined as TOutcome;
                  } else {
                    const decoded = decodeOutcome<TOutcome>(payload.outcome, contract.outcomeFields);
                    if (decoded === undefined) {
                      return statusTransportResult('unexpected_response');
                    }
                    outcome = decoded;
                  }
                  const data = Object.freeze({ ...common, state: 'completed' as const, outcome });
                  return Object.freeze({ ok: true, kind: 'completed', status: 200, data });
                }
                case 'rejected': {
                  if (
                    !hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state', 'error'])
                    || !hasNoRetryAfter(response)
                    || !isRecord(payload.error)
                    || !hasExactKeys(payload.error, ['category', 'code'])
                    || !isNonEmptyString(payload.error.category)
                    || !isNonEmptyString(payload.error.code)
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  const error = Object.freeze({ category: payload.error.category, code: payload.error.code });
                  const data = Object.freeze({ ...common, state: 'rejected' as const, error });
                  return Object.freeze({ ok: true, kind: 'rejected', status: 200, data });
                }
                case 'failed': {
                  if (
                    !hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state', 'error'])
                    || !hasNoRetryAfter(response)
                    || !isRecord(payload.error)
                    || !hasExactKeys(payload.error, ['code'])
                    || payload.error.code !== 'operation_failed'
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  const data = Object.freeze({
                    ...common,
                    state: 'failed' as const,
                    error: Object.freeze({ code: 'operation_failed' as const }),
                  });
                  return Object.freeze({ ok: true, kind: 'failed', status: 200, data });
                }
                case 'dead_lettered': {
                  if (
                    !hasExactKeys(payload, ['schemaVersion', 'operationId', 'operationType', 'state', 'error'])
                    || !hasNoRetryAfter(response)
                    || !isRecord(payload.error)
                    || !hasExactKeys(payload.error, ['code'])
                    || payload.error.code !== 'operation_dead_lettered'
                  ) {
                    return statusTransportResult('unexpected_response');
                  }
                  const data = Object.freeze({
                    ...common,
                    state: 'dead_lettered' as const,
                    error: Object.freeze({ code: 'operation_dead_lettered' as const }),
                  });
                  return Object.freeze({ ok: true, kind: 'dead_lettered', status: 200, data });
                }
                default:
                  return statusTransportResult('unexpected_response');
              }
            }

            function decodeExactStatusError<TType extends string, TOutcome>(
              payload: Readonly<Record<string, unknown>>,
              status: 404 | 410 | 500,
              kind: 'unavailable' | 'expired' | 'internal',
              code: 'operation_unavailable' | 'operation_expired' | 'internal_error',
            ): OperationStatusResult<TType, TOutcome> {
              if (!hasExactKeys(payload, ['status', 'code']) || payload.status !== 'error' || payload.code !== code) {
                return statusTransportResult('unexpected_response');
              }

              if (status === 404 && kind === 'unavailable' && code === 'operation_unavailable') {
                return Object.freeze({ ok: false, kind, status, error: Object.freeze({ code }) });
              }
              if (status === 410 && kind === 'expired' && code === 'operation_expired') {
                return Object.freeze({ ok: false, kind, status, error: Object.freeze({ code }) });
              }
              if (status === 500 && kind === 'internal' && code === 'internal_error') {
                return Object.freeze({ ok: false, kind, status, error: Object.freeze({ code }) });
              }

              return statusTransportResult('unexpected_response');
            }

            function decodeRetryAfterSeconds(response: OperationFetchResponseSnapshot): number | undefined {
              let value: unknown;
              try {
                value = response.getHeader('retry-after');
              } catch {
                return undefined;
              }
              if (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value)) {
                return undefined;
              }
              const seconds = Number(value);
              return Number.isSafeInteger(seconds) && seconds > 0 ? seconds : undefined;
            }

            function hasNoRetryAfter(response: OperationFetchResponseSnapshot): boolean {
              try {
                return response.getHeader('retry-after') === null;
              } catch {
                return false;
              }
            }

            function isPositiveSafeInteger(value: unknown): value is number {
              return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
            }

            function isUtcMicrosecondTimestamp(value: unknown): value is string {
              if (typeof value !== 'string') {
                return false;
              }
              const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\.(\d{6})Z$/.exec(value);
              if (match === null) {
                return false;
              }
              const year = Number(match[1]);
              const month = Number(match[2]);
              const day = Number(match[3]);
              const hour = Number(match[4]);
              const minute = Number(match[5]);
              const second = Number(match[6]);
              if (month < 1 || month > 12 || hour > 23 || minute > 59 || second > 59) {
                return false;
              }
              const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
              const days = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
              return day >= 1 && day <= days[month - 1];
            }

            function decodeOutcome<TOutcome>(
              payload: Readonly<Record<string, unknown>>,
              fields: readonly OperationOutcomeField[],
            ): TOutcome | undefined {
              if (!hasExactKeys(payload, fields.map((field): string => field.name))) {
                return undefined;
              }
              for (const field of fields) {
                if (!isOutcomeScalar(payload[field.name], field)) {
                  return undefined;
                }
              }

              return Object.freeze({ ...payload }) as TOutcome;
            }

            function decodeAcknowledgement(
              payload: Readonly<Record<string, unknown>>,
            ): DeferredOperationResult<never> {
              if (
                !hasExactKeys(payload, ['status', 'operationId', 'acceptedAt'])
                || payload.status !== 'accepted'
                || !isNonEmptyString(payload.operationId)
                || !isNonEmptyString(payload.acceptedAt)
              ) {
                return transportResult('unexpected_response');
              }

              return Object.freeze({
                ok: true,
                kind: 'accepted',
                status: 202,
                data: Object.freeze({ operationId: payload.operationId, acceptedAt: payload.acceptedAt }),
              });
            }

            function decodeProtocolError(
              payload: Readonly<Record<string, unknown>>,
            ): OperationFailureResult<never> {
              if (
                !hasExactKeys(payload, ['status', 'code'])
                || payload.status !== 'error'
                || !isNonEmptyString(payload.code)
              ) {
                return transportResult('unexpected_response');
              }

              return Object.freeze({
                ok: false,
                kind: 'protocol',
                status: 400,
                error: Object.freeze({ code: payload.code }),
              });
            }

            function decodeRejection(
              payload: Readonly<Record<string, unknown>>,
              status: number,
            ): OperationFailureResult<never> {
              const category = rejectionCategory(status);
              const middlewareShape = status === 401 && payload.status === 'error';
              if (
                category === undefined
                || !hasExactKeys(payload, ['status', 'category', 'code'], middlewareShape ? [] : ['operationId'])
                || (middlewareShape ? payload.status !== 'error' : payload.status !== 'rejected')
                || payload.category !== category
                || !isNonEmptyString(payload.code)
                || (
                  Object.prototype.hasOwnProperty.call(payload, 'operationId')
                  && !isNonEmptyString(payload.operationId)
                )
              ) {
                return transportResult('unexpected_response');
              }

              const error: OperationRejection = Object.prototype.hasOwnProperty.call(payload, 'operationId')
                ? Object.freeze({ category, code: payload.code, operationId: payload.operationId as string })
                : Object.freeze({ category, code: payload.code });

              return Object.freeze({
                ok: false,
                kind: 'rejected',
                status: status as 400 | 401 | 403 | 404 | 409,
                error,
              });
            }

            function decodeValidation<TField extends string>(
              payload: Readonly<Record<string, unknown>>,
              validationFields: readonly TField[],
            ): OperationFailureResult<TField> {
              if (
                !hasExactKeys(payload, ['status', 'operationId', 'category', 'code', 'violations'])
                || payload.status !== 'rejected'
                || payload.category !== 'validation'
                || payload.code !== 'validation.failed'
                || !isNonEmptyString(payload.operationId)
                || !Array.isArray(payload.violations)
              ) {
                return transportResult('unexpected_response');
              }

              const knownFields = new Set<string>(validationFields);
              const violations: ValidationViolation<TField>[] = [];
              for (const violation of payload.violations) {
                if (
                  !isRecord(violation)
                  || !hasExactKeys(violation, ['field', 'rule', 'code'])
                  || !isNonEmptyString(violation.field)
                  || !knownFields.has(violation.field)
                  || !isNonEmptyString(violation.rule)
                  || !isNonEmptyString(violation.code)
                ) {
                  return transportResult('unexpected_response');
                }
                violations.push(Object.freeze({
                  field: violation.field as TField,
                  rule: violation.rule,
                  code: violation.code,
                }));
              }

              const error: ValidationRejection<TField> = Object.freeze({
                operationId: payload.operationId,
                code: 'validation.failed',
                violations: Object.freeze(violations),
              });
              return Object.freeze({ ok: false, kind: 'validation', status: 422, error });
            }

            function decodeInternalError(
              payload: Readonly<Record<string, unknown>>,
            ): OperationFailureResult<never> {
              if (
                !hasExactKeys(payload, ['status', 'code'], ['operationId'])
                || payload.status !== 'error'
                || payload.code !== 'internal_error'
                || (
                  Object.prototype.hasOwnProperty.call(payload, 'operationId')
                  && !isNonEmptyString(payload.operationId)
                )
              ) {
                return transportResult('unexpected_response');
              }

              const error = Object.prototype.hasOwnProperty.call(payload, 'operationId')
                ? Object.freeze({ code: 'internal_error' as const, operationId: payload.operationId as string })
                : Object.freeze({ code: 'internal_error' as const });
              return Object.freeze({ ok: false, kind: 'internal', status: 500, error });
            }

            function rejectionCategory(status: number): OperationRejection['category'] | undefined {
              switch (status) {
                case 400: return 'business_rule';
                case 401: return 'unauthorized';
                case 403: return 'forbidden';
                case 404: return 'not_found';
                case 409: return 'conflict';
                default: return undefined;
              }
            }

            function isOutcomeScalar(value: unknown, field: OperationOutcomeField): boolean {
              if (value === null) {
                return field.nullable;
              }
              switch (field.type) {
                case 'string': return typeof value === 'string';
                case 'integer': return typeof value === 'number' && Number.isSafeInteger(value);
                case 'float': return typeof value === 'number' && Number.isFinite(value);
                case 'boolean': return typeof value === 'boolean';
              }
            }

            function isJsonMediaType(value: unknown): boolean {
              return typeof value === 'string'
                && value.split(';', 1)[0].trim().toLowerCase() === 'application/json';
            }

            function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
              return typeof value === 'object' && value !== null && !Array.isArray(value);
            }

            function hasExactKeys(
              value: Readonly<Record<string, unknown>>,
              required: readonly string[],
              optional: readonly string[] = [],
            ): boolean {
              const allowed = new Set([...required, ...optional]);
              return required.every((key): boolean => Object.prototype.hasOwnProperty.call(value, key))
                && Object.keys(value).every((key): boolean => allowed.has(key));
            }

            function isNonEmptyString(value: unknown): value is string {
              return typeof value === 'string' && value.length > 0;
            }

            function isCanonicalOperationId(value: unknown): value is string {
              return typeof value === 'string'
                && /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(value);
            }

            function transportResult(
              code: 'missing_fetch' | 'invalid_base_url' | 'network_error' | 'aborted' | 'unexpected_response',
            ): OperationFailureResult<never> {
              return Object.freeze({
                ok: false,
                kind: 'transport',
                status: null,
                error: Object.freeze({ code }),
              });
            }

            function statusTransportResult(
              code:
                | 'invalid_operation_id'
                | 'missing_fetch'
                | 'invalid_base_url'
                | 'network_error'
                | 'aborted'
                | 'unexpected_response',
            ): OperationStatusResult<never, never> {
              return Object.freeze({
                ok: false,
                kind: 'transport',
                status: null,
                error: Object.freeze({ code }),
              });
            }

            function waitTransportResult(
              code:
                | 'invalid_operation_id'
                | 'missing_fetch'
                | 'invalid_base_url'
                | 'network_error'
                | 'aborted'
                | 'invalid_wait_options'
                | 'poll_timeout'
                | 'unexpected_response',
            ): OperationWaitResult<never, never> {
              return Object.freeze({
                ok: false,
                kind: 'transport',
                status: null,
                error: Object.freeze({ code }),
              });
            }
            CLIENT . "\n";
    }

    /** @mago-expect lint:halstead */
    private function operationSource(FrontendOperationContract $operation): string
    {
        $valueName = $operation->exportName . 'Value';
        $urlName = $operation->exportName . 'UrlParameters';
        $outcomeName = $operation->exportName . 'Outcome';
        $fieldName = $operation->exportName . 'Field';
        $resultName = $operation->exportName . 'Result';
        $statusResultName = $operation->exportName . 'StatusResult';
        $waitResultName = $operation->exportName . 'WaitResult';
        $urlFields = array_values(array_filter(
            $operation->value->fields,
            static fn(FrontendValueFieldContract $field): bool => in_array(
                $field->source,
                ['path', 'query'],
                strict: true,
            ),
        ));
        $import = str_repeat('../', substr_count(haystack: dirname($operation->module), needle: '/') + 1);
        $resultType = match (true) {
            $operation->strategy === 'deferred' => 'DeferredOperationResult',
            $operation->outcome->mode === 'void' => 'InlineVoidOperationResult',
            default => 'InlineOutcomeOperationResult',
        };
        /** @var list<string> $lines */
        $lines = [
            sprintf(
                "import { buildOperationRequest, buildOperationUrl, fetchOperation, fetchOperationStatus, waitForOperationStatus } from '%sclient';",
                $import,
            ),
            sprintf(
                "import type { %s, OperationCallOptions, OperationRequest, OperationRequestOptions, OperationStatusResult, OperationWaitOptions, OperationWaitResult } from '%stypes';",
                $resultType,
                $import,
            ),
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
        foreach ($this->outcomeTypeDeclaration($outcomeName, $operation) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = sprintf(
            'export type %s = %s;',
            $fieldName,
            $operation->value->fields === []
                ? 'never'
                : implode(' | ', array_map(
                    fn(FrontendValueFieldContract $field): string => $this->json($field->name),
                    $operation->value->fields,
                )),
        );
        $lines[] = sprintf('export type %s = %s;', $resultName, match ($resultType) {
            'DeferredOperationResult' => sprintf('%s<%s>', $resultType, $fieldName),
            'InlineVoidOperationResult' => sprintf('%s<%s>', $resultType, $fieldName),
            default => sprintf('%s<%s, %s>', $resultType, $outcomeName, $fieldName),
        });
        $lines[] = sprintf(
            'export type %s = OperationStatusResult<%s, %s>;',
            $statusResultName,
            $this->json($operation->typeId),
            $outcomeName,
        );
        $lines[] = sprintf(
            'export type %s = OperationWaitResult<%s, %s>;',
            $waitResultName,
            $this->json($operation->typeId),
            $outcomeName,
        );

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
        $lines[] = 'const responseContract = Object.freeze({';
        $responseMode = match (true) {
            $operation->strategy === 'deferred' => 'deferred',
            $operation->outcome->mode === 'void' => 'inline_void',
            default => 'inline_outcome',
        };
        $lines[] = sprintf('  mode: %s as const,', $this->json($responseMode));
        if ($operation->strategy === 'inline' && $operation->outcome->mode === 'outcome') {
            $lines[] = '  outcomeFields: Object.freeze([';
            foreach ($operation->outcome->fields as $field) {
                $lines[] =
                    '    Object.freeze('
                    . $this->json([
                        'name' => $field->name,
                        'type' => $field->type,
                        'nullable' => $field->nullable,
                    ])
                    . '),';
            }
            $lines[] = '  ] as const),';
        }
        $lines[] = '  validationFields: Object.freeze([';
        foreach ($operation->value->fields as $field) {
            $lines[] = sprintf('    %s,', $this->json($field->name));
        }
        $lines[] = '  ] as const),';
        $lines[] = '});';
        $lines[] = '';
        $lines[] = 'const statusResponseContract = Object.freeze({';
        $lines[] = sprintf('  operationType: %s as const,', $this->json($operation->typeId));
        $lines[] = sprintf('  outcomeMode: %s as const,', $this->json($operation->outcome->mode));
        $lines[] = '  outcomeFields: Object.freeze([';
        foreach ($operation->outcome->fields as $field) {
            $lines[] =
                '    Object.freeze('
                . $this->json([
                    'name' => $field->name,
                    'type' => $field->type,
                    'nullable' => $field->nullable,
                ])
                . '),';
        }
        $lines[] = '  ] as const),';
        $lines[] = '});';
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
        $lines[] = sprintf(
            '  status(operationId: string, options?: OperationCallOptions): Promise<%s> {',
            $statusResultName,
        );
        $lines[] = sprintf(
            '    return fetchOperationStatus<%s, %s>(operationId, options, statusResponseContract);',
            $this->json($operation->typeId),
            $outcomeName,
        );
        $lines[] = '  },';
        $lines[] = sprintf(
            '  wait(operationId: string, options: OperationWaitOptions): Promise<%s> {',
            $waitResultName,
        );
        $lines[] = sprintf(
            '    return waitForOperationStatus<%s, %s>(operationId, options, statusResponseContract);',
            $this->json($operation->typeId),
            $outcomeName,
        );
        $lines[] = '  },';
        $lines[] = sprintf(
            '  fetch(value: %s, options?: OperationCallOptions): Promise<%s> {',
            $valueName,
            $resultName,
        );
        $lines[] = sprintf('    return fetchOperation<%s, %s>(', $outcomeName, $fieldName);
        $lines[] = sprintf(
            '      %s, %s, bindings, value, options, responseContract,',
            $this->json($operation->method),
            $this->json($operation->path),
        );
        $lines[] = '    );';
        $lines[] = '  },';
        $lines[] = '});';

        return implode("\n", $lines) . "\n";
    }

    /** @return list<string> */
    private function outcomeTypeDeclaration(string $name, FrontendOperationContract $operation): array
    {
        if ($operation->outcome->mode === 'void') {
            return [sprintf('export type %s = undefined;', $name)];
        }
        if ($operation->outcome->fields === []) {
            return [sprintf('export type %s = Readonly<Record<never, never>>;', $name)];
        }

        $lines = [sprintf('export type %s = Readonly<{', $name)];
        foreach ($operation->outcome->fields as $field) {
            $type = match ($field->type) {
                'string' => 'string',
                'integer', 'float' => 'number',
                'boolean' => 'boolean',
                default => throw new InvalidArgumentException('Frontend outcome scalar kind is invalid.'),
            };
            $lines[] = sprintf(
                '  readonly %s: %s;',
                $this->propertyName($field->name),
                $type . ($field->nullable ? ' | null' : ''),
            );
        }
        $lines[] = '}>;';

        return $lines;
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
            || !in_array($operation->strategy, ['inline', 'deferred'], strict: true)
            || !in_array($operation->outcome->mode, ['outcome', 'void'], strict: true)
            || $operation->outcome->mode === 'void' && $operation->outcome->fields !== []
        ) {
            throw new InvalidArgumentException('Frontend operation generation metadata is invalid.');
        }
        $valueNames = [];
        foreach ($operation->value->fields as $field) {
            if (
                $field->name === ''
                || in_array($field->name, $valueNames, strict: true)
                || str_contains($field->transportName, "\r")
                || str_contains($field->transportName, "\n")
            ) {
                throw new InvalidArgumentException('Frontend operation transport name is invalid.');
            }
            $valueNames[] = $field->name;
            if ($field->source === 'path' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $field->transportName) !== 1) {
                throw new InvalidArgumentException('Frontend operation path transport name is invalid.');
            }
            if (in_array($operation->method, ['GET', 'HEAD'], strict: true) && $field->source === 'body') {
                throw new InvalidArgumentException('Frontend operation does not permit a body for GET or HEAD.');
            }
        }
        $outcomeNames = [];
        foreach ($operation->outcome->fields as $field) {
            if ($field->name === '' || in_array($field->name, $outcomeNames, strict: true)) {
                throw new InvalidArgumentException('Frontend operation outcome field is invalid.');
            }
            $outcomeNames[] = $field->name;
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

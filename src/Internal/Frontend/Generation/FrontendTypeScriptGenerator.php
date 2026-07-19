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
            sprintf("import { buildOperationRequest, buildOperationUrl, fetchOperation } from '%sclient';", $import),
            sprintf(
                "import type { %s, OperationCallOptions, OperationRequest, OperationRequestOptions } from '%stypes';",
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

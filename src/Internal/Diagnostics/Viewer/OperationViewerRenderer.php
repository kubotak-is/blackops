<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

use BlackOps\Internal\Diagnostics\DiagnosticsSafeActor;
use BlackOps\Internal\Diagnostics\OperationDiagnostics;

final readonly class OperationViewerRenderer
{
    public function form(): string
    {
        return $this->page(
            'Operation diagnostics',
            '<form method="get" action="/">'
            . '<label>Operation ID <input name="operationId" required autocomplete="off"></label>'
            . '<button type="submit">Inspect</button></form>',
        );
    }

    /** @mago-expect lint:halstead */
    public function found(OperationDiagnostics $diagnostics): string
    {
        $identity = $diagnostics->identity;
        $availability = $diagnostics->availability;
        $timeline = '';
        foreach ($diagnostics->timeline as $entry) {
            $timeline .=
                '<tr><td>'
                . $entry->sequence
                . '</td><td>'
                . $this->escape($entry->occurredAt)
                . '</td><td>'
                . $this->escape($entry->event)
                . '</td><td>'
                . $this->escape($entry->attemptId ?? 'none')
                . '</td><td>'
                . $this->data($entry->data)
                . '</td></tr>';
        }
        $attempts = '';
        foreach ($diagnostics->attempts as $attempt) {
            $attempts .=
                '<li>#'
                . $attempt->number
                . ' '
                . $this->escape($attempt->attemptId)
                . ' at '
                . $this->escape($attempt->startedAt)
                . ' — sequences '
                . implode(', ', $attempt->events)
                . '</li>';
        }
        $actors = $identity->actors;
        $outcome = $diagnostics->outcome === null
            ? '<p>Not available</p>'
            : '<dl><dt>Type</dt><dd>'
            . $this->escape($diagnostics->outcome->type)
            . '</dd><dt>Completed</dt><dd>'
            . $this->escape($diagnostics->outcome->completedAt ?? 'none')
            . '</dd><dt>Source</dt><dd>'
            . $this->escape($diagnostics->outcome->source)
            . '</dd></dl><pre>'
            . $this->data($diagnostics->outcome->data)
            . '</pre>';

        return $this->page(
            'Operation ' . $identity->operationId,
            '<p><a href="/">Inspect another operation</a></p>'
            . '<h2>Summary</h2><dl><dt>ID</dt><dd>'
            . $this->escape($identity->operationId)
            . '</dd><dt>Type</dt><dd>'
            . $this->escape($identity->type)
            . '</dd><dt>Strategy</dt><dd>'
            . $this->escape($identity->strategy)
            . '</dd><dt>Correlation ID</dt><dd>'
            . $this->escape($identity->correlationId ?? 'none')
            . '</dd><dt>Causation ID</dt><dd>'
            . $this->escape($identity->causationId ?? 'none')
            . '</dd><dt>Schema version</dt><dd>'
            . $identity->schemaVersion
            . '</dd><dt>State</dt><dd>'
            . $this->escape($diagnostics->state->current->value)
            . '</dd><dt>Terminal</dt><dd>'
            . ($diagnostics->state->terminal ? 'yes' : 'no')
            . '</dd><dt>Authority</dt><dd>'
            . $this->escape($diagnostics->state->source)
            . '</dd></dl>'
            . '<h2>Availability</h2><dl><dt>Transport payload</dt><dd>'
            . $availability->transportPayload->value
            . '</dd><dt>Journal</dt><dd>'
            . $availability->journal->value
            . '</dd><dt>Outcome</dt><dd>'
            . $availability->outcome->value
            . '</dd><dt>Dead letter</dt><dd>'
            . $availability->deadLetter->value
            . '</dd></dl>'
            . '<h2>Actors</h2><dl><dt>Origin</dt><dd>'
            . $this->actor($actors?->origin)
            . '</dd><dt>Authorization</dt><dd>'
            . $this->actor($actors?->authorization)
            . '</dd><dt>Execution</dt><dd>'
            . $this->actor($actors?->execution)
            . '</dd></dl>'
            . '<h2>Timeline</h2><table><thead><tr><th>#</th><th>Time</th><th>Event</th><th>Attempt</th><th>Data</th></tr></thead><tbody>'
            . $timeline
            . '</tbody></table><h2>Attempts</h2><ul>'
            . $attempts
            . '</ul><h2>Outcome</h2>'
            . $outcome,
        );
    }

    public function notFound(): string
    {
        return $this->page('Not found', '<p>The requested resource is unavailable.</p>');
    }

    public function badRequest(): string
    {
        return $this->page('Bad request', '<p>The request could not be processed.</p>');
    }

    public function methodNotAllowed(): string
    {
        return $this->page('Method not allowed', '<p>This method is not allowed.</p>');
    }

    public function internalError(): string
    {
        return $this->page('Internal error', '<p>Diagnostics are temporarily unavailable.</p>');
    }

    private function actor(?DiagnosticsSafeActor $actor): string
    {
        return $actor === null ? 'none' : $this->escape($actor->id) . ' (' . $this->escape($actor->type) . ')';
    }

    /** @param array<string, mixed> $data */
    private function data(array $data): string
    {
        return $this->escape(json_encode((object) $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function escape(string $value): string
    {
        $value =
            preg_replace_callback(
                '/\p{Cc}/u',
                static fn(array $matches): string => sprintf('\\u%04x', mb_ord(string: $matches[0], encoding: 'UTF-8')),
                $value,
            ) ?? '';

        return htmlspecialchars(string: $value, flags: ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, encoding: 'UTF-8');
    }

    private function page(string $title, string $content): string
    {
        return (
            '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            . '<title>'
            . $this->escape($title)
            . '</title><style>body{font:16px system-ui;max-width:1100px;margin:2rem auto;padding:0 1rem;color:#17202a}'
            . 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccd;padding:.45rem;text-align:left;vertical-align:top}'
            . 'dt{font-weight:700}dd{margin:0 0 .7rem}input{width:26rem;max-width:95%;padding:.5rem}button{padding:.55rem;margin-left:.5rem}'
            . 'pre{white-space:pre-wrap;overflow-wrap:anywhere}</style><body><header><h1>'
            . $this->escape($title)
            . '</h1></header><main>'
            . $content
            . '</main></body></html>'
        );
    }
}

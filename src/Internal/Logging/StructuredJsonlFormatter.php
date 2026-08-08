<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Encodes the framework's structured record envelope instead of Monolog's
 * implementation-shaped record array.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class StructuredJsonlFormatter extends JsonFormatter
{
    public function __construct(
        private SensitiveProjectionFilter $sensitive = new SensitiveProjectionFilter(),
    ) {
        parent::__construct(self::BATCH_MODE_NEWLINES, appendNewline: true, ignoreEmptyContextAndExtra: false);
    }

    public function format(LogRecord $record): string
    {
        $context = $record->context;
        $kind = in_array($context['kind'] ?? null, ['application', 'framework', 'audit'], strict: true)
            ? $context['kind']
            : 'application';
        $schemaVersion = 1;
        if ($kind === 'audit') {
            $output = [
                'schemaVersion' => $schemaVersion,
                'kind' => 'audit',
                'occurredAt' => $this->auditOccurredAt($context['occurredAt'] ?? null, $record),
                'event' => is_string($context['event'] ?? null) ? $context['event'] : 'audit.recorded',
                'data' => is_array($context['data'] ?? null)
                    ? $this->sensitive->projectArray($context['data'])
                    : new \stdClass(),
            ];

            return $this->toJson($output, true) . "\n";
        }

        $projectedContext = is_array($context['context'] ?? null)
            ? $this->sensitive->projectArray($context['context'])
            : $this->sensitive->projectArray($context);
        if (array_is_list($projectedContext)) {
            $projectedContext = (object) $projectedContext;
        }

        $output = [
            'schemaVersion' => $schemaVersion,
            'kind' => $kind,
            'occurredAt' => $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'level' => strtolower($record->level->getName()),
            'message' => $record->message,
            'channel' => $record->channel,
            'context' => $projectedContext === [] ? new \stdClass() : $projectedContext,
        ];

        if (is_array($context['operation'] ?? null)) {
            $output['operation'] = $this->sensitive->projectArray($context['operation']);
        }
        if (array_key_exists('attempt', $context)) {
            $output['attempt'] = $this->attempt($context['attempt']);
        }
        if (is_array($context['telemetry'] ?? null)) {
            $output['telemetry'] = $this->sensitive->projectArray($context['telemetry']);
        }

        return $this->toJson($output, true) . "\n";
    }

    private function attempt(mixed $attempt): mixed
    {
        if ($attempt === null) {
            return null;
        }
        if (!is_array($attempt)) {
            return new \stdClass();
        }

        return $this->sensitive->projectArray($attempt);
    }

    private function auditOccurredAt(mixed $occurredAt, LogRecord $record): string
    {
        if ($occurredAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($occurredAt)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
        }

        return $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}

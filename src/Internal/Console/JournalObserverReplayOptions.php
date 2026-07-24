<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Typed command options after validating selector and confirmation boundaries.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class JournalObserverReplayOptions
{
    /** @param list<string> $observers */
    public function __construct(
        public bool $dryRun,
        public ?PostgreSqlObserverReplaySelector $selector,
        public array $observers,
        public int $batchSize,
        public ?string $checkpoint,
        public ?string $resume,
        public ?string $actor,
        public ?string $reason,
    ) {}

    public static function fromInput(InputInterface $input): self
    {
        $dryRun = self::booleanOption($input, 'dry-run');
        $confirm = self::booleanOption($input, 'confirm');
        if ($dryRun === $confirm) {
            throw new InvalidArgumentException('Replay requires exactly one of --dry-run or --confirm.');
        }

        $resume = self::nullableString($input->getOption('resume'));
        $selector = $resume === null ? self::selector($input) : null;
        $observers = $resume === null ? self::observers($input) : [];
        $batchSize = self::batchSize($input->getOption('batch-size'));
        self::validateResumeOptions($input, $resume);
        $checkpoint = self::nullableString($input->getOption('checkpoint'));
        if (!$dryRun && $resume === null && $checkpoint === null) {
            throw new InvalidArgumentException('Replay checkpoint is required for a new confirmation.');
        }
        if ($dryRun && $resume !== null) {
            throw new InvalidArgumentException('--dry-run cannot be combined with --resume.');
        }

        return new self(
            $dryRun,
            $selector,
            $observers,
            $batchSize,
            $resume ?? $checkpoint,
            $resume,
            self::nullableString($input->getOption('actor')),
            self::nullableString($input->getOption('reason')),
        );
    }

    private static function selector(InputInterface $input): PostgreSqlObserverReplaySelector
    {
        $operation = self::nullableString($input->getOption('operation-id'));
        $record = self::nullableString($input->getOption('record-id'));
        $from = self::nullableString($input->getOption('from'));
        $to = self::nullableString($input->getOption('to'));
        $selectorCount =
            ($operation !== null ? 1 : 0) + ($record !== null ? 1 : 0) + ($from !== null || $to !== null ? 1 : 0);
        if ($selectorCount !== 1) {
            throw new InvalidArgumentException('Replay requires exactly one selector.');
        }
        if ($operation !== null) {
            return PostgreSqlObserverReplaySelector::operation(OperationId::fromString($operation));
        }
        if ($record !== null) {
            return PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString($record));
        }
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Replay time selector requires both --from and --to.');
        }
        return PostgreSqlObserverReplaySelector::time(self::timestamp($from), self::timestamp($to));
    }

    /** @return list<string> */
    private static function observers(InputInterface $input): array
    {
        $value = self::optionArray($input->getOption('observer'));
        $observers = [];
        foreach ($value as $name) {
            if (trim($name) === '') {
                continue;
            }
            $observers[] = $name;
        }
        if ($observers === []) {
            throw new InvalidArgumentException('Replay requires at least one --observer target.');
        }
        return array_values(array_unique($observers));
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            throw new InvalidArgumentException('Replay time selector must use RFC3339 timestamps.');
        }
        try {
            $result = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new InvalidArgumentException('Replay time selector contains an invalid calendar date.');
            }
            return $result;
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Replay time selector must use RFC3339 timestamps.', 0, $exception);
        }
    }

    private static function batchSize(mixed $value): int
    {
        $value = self::nullableString($value);
        if ($value === null || !ctype_digit($value)) {
            throw new InvalidArgumentException('Replay batch size must be a positive integer.');
        }
        $batchSize = (int) $value;
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('Replay batch size must be between 1 and 1000.');
        }
        return $batchSize;
    }

    private static function validateResumeOptions(InputInterface $input, ?string $resume): void
    {
        if ($resume === null) {
            return;
        }
        foreach (['operation-id', 'record-id', 'from', 'to', 'observer', 'checkpoint'] as $option) {
            if ($input->getOption($option) !== null && $input->getOption($option) !== []) {
                throw new InvalidArgumentException('--resume cannot be combined with selector or observer options.');
            }
        }
    }

    private static function booleanOption(InputInterface $input, string $name): bool
    {
        return $input->getOption($name) === true;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function optionArray(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new InvalidArgumentException('Replay requires at least one --observer target.');
        }
        return array_values(array_map(static function (mixed $item): string {
            if (!is_string($item)) {
                throw new InvalidArgumentException('Replay observer targets must be strings.');
            }
            return $item;
        }, $value));
    }
}

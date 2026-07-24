<?php

declare(strict_types=1);

namespace BlackOps\Internal\Replay;

use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use InvalidArgumentException;

final readonly class ObserverReplayTargetRegistry
{
    /** @param list<JournalObserverBinding> $bindings */
    public function __construct(
        private array $bindings,
    ) {
        $names = [];
        foreach ($bindings as $binding) {
            if (array_key_exists($binding->name(), $names)) {
                throw new InvalidArgumentException('Replay observer target names must be unique.');
            }
            $names[$binding->name()] = true;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_map(static fn(JournalObserverBinding $binding): string => $binding->name(), $this->bindings);
        sort($names);
        return $names;
    }

    /** @param list<string> $requested @return list<JournalObserverBinding> */
    public function resolve(array $requested): array
    {
        $map = [];
        foreach ($this->bindings as $binding) {
            $map[$binding->name()] = $binding;
        }
        $names = array_values(array_unique($requested));
        sort($names);
        if ($names === []) {
            throw new InvalidArgumentException('Replay requires at least one observer target.');
        }
        /** @var list<JournalObserverBinding> $resolved */
        $resolved = [];
        foreach ($names as $name) {
            if (!array_key_exists($name, $map)) {
                throw new InvalidArgumentException('Unknown or disabled replay observer target.');
            }
            $resolved[] = $map[$name];
        }
        return $resolved;
    }

    public function observe(JournalObserverBinding $binding, ObservedJournalRecord $record): void
    {
        $binding->observer()->observe($record);
    }

    public function flush(JournalObserverBinding $binding): void
    {
        $observer = $binding->observer();
        if ($observer instanceof FlushableJournalObserver) {
            $observer->flush();
        }
    }
}

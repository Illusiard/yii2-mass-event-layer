<?php

namespace illusiard\massEvents\components\filters;

final class IgnoreTablesFilter extends BasePatternFilter
{
    #[\Override]
    protected function getHaystackString(array $event): ?string
    {
        $payload = $event['payload'] ?? null;
        if (!is_array($payload)) {
            return null;
        }

        $table = $payload['table'] ?? null;

        return is_string($table) ? $table : null;
    }
}

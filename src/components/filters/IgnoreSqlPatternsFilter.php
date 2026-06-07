<?php

namespace illusiard\massEvents\components\filters;

final class IgnoreSqlPatternsFilter extends BasePatternFilter
{
    #[\Override]
    protected function getHaystackString(array $event): ?string
    {
        $payload = $event['payload'] ?? null;
        if (!is_array($payload)) {
            return null;
        }

        $sql = $payload['sql'] ?? null;

        return is_string($sql) ? $sql : null;
    }
}

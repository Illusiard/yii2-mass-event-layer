<?php

namespace illusiard\massEvents\components\filters;

final class IgnoreTablesFilter extends BasePatternFilter
{
    protected function getHaystackString(array $event): ?string
    {
        return $event['payload']['table'] ?? null;
    }
}

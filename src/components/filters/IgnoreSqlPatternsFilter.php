<?php

namespace illusiard\massEvents\components\filters;

final class IgnoreSqlPatternsFilter extends BasePatternFilter
{
    protected function getHaystackString(array $event): ?string
    {
        return $event['payload']['sql'] ?? null;
    }
}

<?php

namespace illusiard\massEvents\components\filters;

use Exception;
use illusiard\massEvents\models\interfaces\FilterInterface;
use Yii;

abstract class BasePatternFilter implements FilterInterface
{
    public const MODE_EXCEPT = 'except';
    public const MODE_ONLY   = 'only';

    /** @var array<string, true> */
    protected array $exact = [];

    /** @var array<int, string> */
    protected array $regex = [];

    protected string $mode;

    /**
     * @param array<int, string>|array{only?:array<int,string>, except?:array<int,string>} $configOrPatterns
     */
    public function __construct(array $configOrPatterns)
    {
        $this->mode = isset($configOrPatterns['only']) ? self::MODE_ONLY : self::MODE_EXCEPT;
        $patterns   = match (true) {
            array_is_list($configOrPatterns)   => $configOrPatterns,
            isset($configOrPatterns['only'])   => $configOrPatterns['only'],
            isset($configOrPatterns['except']) => $configOrPatterns['except'],
            default                            => []
        };

        $this->compilePatterns($patterns);
    }

    abstract protected function getHaystackString(array $event): ?string;

    public function shouldPublish(array $event): bool
    {
        $haystack = $this->getHaystackString($event);
        if (!is_string($haystack) || $haystack === '') {
            // no haystack -> ignore
            return true;
        }

        $matched = $this->matches($haystack);

        if ($this->mode === self::MODE_ONLY) {
            return $matched;
        }

        // MODE_EXCEPT
        return !$matched;
    }

    /** @param array<int, string> $patterns */
    protected function compilePatterns(array $patterns): void
    {
        foreach ($patterns as $p) {
            $p = trim((string)$p);
            if ($p === '') {
                continue;
            }

            if ($this->looksLikeRegex($p)) {
                $this->regex[] = $p;
                continue;
            }

            if (strpbrk($p, '*?') !== false) {
                $this->regex[] = $this->wildcardToRegex($p);
                continue;
            }

            $this->exact[$p] = true;
        }
    }

    protected function matches(string $haystack): bool
    {
        if (isset($this->exact[$haystack])) {
            return true;
        }

        foreach ($this->regex as $pattern) {
            try {
                $pattern = (string)$pattern;
                if ($pattern === '') {
                    continue;
                }
                if (preg_match($pattern, $haystack) === 1) {
                    return false;
                }
            } catch (Exception $e) {
                Yii::error($e->getMessage(), __METHOD__);
                continue;
            }
        }

        return false;
    }

    protected function looksLikeRegex(string $pattern): bool
    {
        if (strlen($pattern) < 3) {
            return false;
        }
        $delim = $pattern[0];
        if (ctype_alnum($delim)) {
            return false;
        }
        $last = strrpos($pattern, $delim);

        return $last !== false && $last > 0;
    }

    protected function wildcardToRegex(string $wildcard): string
    {
        $quoted = preg_quote($wildcard, '/');
        $quoted = str_replace(['\*', '\?'], ['.*', '.'], $quoted);

        return '/^' . $quoted . '$/i';
    }
}
<?php

namespace App\Services\Claude;

/**
 * Ответ модели вместе с расходом токенов.
 */
readonly class ClaudeResult
{
    public function __construct(
        public array $data,
        public array $raw,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedTokens,
        public int $cost,      // в сотых доли цента
        public string $model,
    ) {}
}

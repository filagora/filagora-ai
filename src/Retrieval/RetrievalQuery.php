<?php

declare(strict_types=1);

namespace Filagora\Ai\Retrieval;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a retrieval source is asked: the person's message, already embedded once for every
 * source to share, and who is asking, so a source can leave out what that person may not see.
 */
final readonly class RetrievalQuery
{
    /**
     * @param  list<float>  $vector  the message, embedded
     * @param  string|null  $versionHint  earlier text from the same conversation, for a source that
     *                                    needs context the message itself may not carry
     * @param  array<string, mixed>  $options  what the caller asks of sources: `limits` (source key
     *                                         => how many to take) and anything a source documents
     */
    public function __construct(
        public string $message,
        public array $vector,
        public ?Authenticatable $user = null,
        public ?string $versionHint = null,
        public array $options = [],
    ) {}

    /**
     * How many items a source should return: the caller's limit for that source, else the default.
     */
    public function limitFor(string $sourceKey, int $default): int
    {
        $limits = is_array($this->options['limits'] ?? null) ? $this->options['limits'] : [];

        return max(1, is_numeric($limits[$sourceKey] ?? null) ? (int) $limits[$sourceKey] : $default);
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
}

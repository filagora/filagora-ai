<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

/**
 * Turns what retrieval found into the part of a system prompt that grounds the model, the same
 * way for the chat and for anything else that answers from excerpts. A host or another package
 * adds the rules for its own kinds of excerpt with `addRules()`.
 */
class GroundingPrompt
{
    /** @var list<string> */
    private array $rules = [];

    public function addRules(string $rules): static
    {
        $this->rules[] = trim($rules);

        return $this;
    }

    /**
     * The rules for using excerpts, one paragraph per registrant.
     */
    public function rules(): string
    {
        return implode("\n\n", $this->rules);
    }

    /**
     * The numbered excerpts, each prefixed with its item's `label` if it has one, then any notes
     * the items carry (once each).
     *
     * @param  list<array<string, mixed>>  $retrievedContext
     */
    public function excerpts(array $retrievedContext): string
    {
        $excerpts = collect($retrievedContext)
            ->map(fn (array $item, int $index): string => sprintf(
                "[%d] %s%s\n%s\n%s",
                $index + 1,
                is_string($item['label'] ?? null) && $item['label'] !== '' ? $item['label'].' ' : '',
                $item['title'],
                $item['excerpt'],
                $item['url'],
            ))
            ->implode("\n\n");

        $notes = collect($retrievedContext)
            ->map(fn (array $item): mixed => $item['note'] ?? null)
            ->filter(fn (mixed $note): bool => is_string($note) && $note !== '')
            ->unique()
            ->map(fn (string $note): string => "\n\n".$note)
            ->implode('');

        return $notes."\n\nRelevant excerpts:\n\n".$excerpts;
    }
}

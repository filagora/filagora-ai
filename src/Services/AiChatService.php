<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Enums\AiChatMessageRole;
use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Models\AiChatMessage;
use Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * One chat turn: keep the person's message, retrieve what grounds an answer, stream the reply
 * from the configured provider, then keep the assembled answer. The system prompt's opening comes
 * from `filagora-ai.chat.system_prompt`, so a host says what the assistant is for.
 */
class AiChatService
{
    /** The width of the title column; the ellipsis a clipped title ends in counts towards it. */
    private const TITLE_LENGTH = 120;

    /** What the opening of the system prompt is unless the host sets `filagora-ai.chat.system_prompt`. */
    public const DEFAULT_SYSTEM_PROMPT = 'You are a helpful assistant. Answer using the excerpts provided below when they are relevant. When you use one, cite it with a markdown link in the form [Title](URL), using that excerpt\'s own title and URL, so the reader can click through to the original. If nothing provided is relevant, say so and answer from general knowledge only if you are confident; otherwise say you do not know rather than guessing.';

    public function __construct(
        private readonly ChatRetrieval $retrieval,
        private readonly ChatProvider $provider,
        private readonly GroundingPrompt $grounding,
    ) {}

    public function startConversation(Authenticatable $user, string $firstMessage): AiChatConversation
    {
        return AiChatConversation::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'title' => Str::limit(trim($firstMessage), self::TITLE_LENGTH - 3),
            'provider' => $this->provider->providerName(),
            'model' => $this->provider->model(),
        ]);
    }

    public function recordUserMessage(AiChatConversation $conversation, string $message): AiChatMessage
    {
        return $conversation->messages()->create([
            'role' => AiChatMessageRole::USER,
            'content' => $message,
        ]);
    }

    /**
     * @return Generator<int, string>
     */
    public function streamAssistantReply(AiChatConversation $conversation, ?Authenticatable $user, string $latestMessage): Generator
    {
        $retrievedContext = $this->retrieval->retrieve($latestMessage, $user, $this->earlierQuestions($conversation));
        $providerMessages = $this->buildProviderMessages($conversation, $retrievedContext);

        $assembled = '';

        try {
            foreach ($this->provider->streamReply($providerMessages, 'ai_chat_reply', (int) $conversation->user_id) as $delta) {
                $assembled .= $delta;

                yield $delta;
            }
        } finally {
            if (trim($assembled) !== '') {
                $conversation->messages()->create([
                    'role' => AiChatMessageRole::ASSISTANT,
                    'content' => $assembled,
                    'retrieved_context' => $retrievedContext,
                ]);
                $conversation->touch();
            }
        }
    }

    /**
     * What the person has asked before, newest first: a detail they gave an hour ago still applies
     * to this question.
     */
    private function earlierQuestions(AiChatConversation $conversation): string
    {
        return $conversation->messages()
            ->where('role', AiChatMessageRole::USER->value)
            ->latest('id')
            ->limit(6)
            ->pluck('content')
            ->implode("\n");
    }

    /**
     * @param  list<array<string, mixed>>  $retrievedContext
     * @return list<array{role: 'system'|'user'|'assistant', content: string}>
     */
    private function buildProviderMessages(AiChatConversation $conversation, array $retrievedContext): array
    {
        $history = $conversation->messages()
            ->get()
            ->map(fn (AiChatMessage $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->all();

        return [
            ['role' => 'system', 'content' => $this->systemPrompt($retrievedContext)],
            ...$history,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $retrievedContext
     */
    private function systemPrompt(array $retrievedContext): string
    {
        $opening = (string) config('filagora-ai.chat.system_prompt') ?: self::DEFAULT_SYSTEM_PROMPT;
        $rules = $this->grounding->rules();
        $prompt = $rules === '' ? $opening : $opening."\n\n".$rules;

        if ($retrievedContext === []) {
            return $prompt."\n\nNo specific content matched this question.";
        }

        return $prompt.$this->grounding->excerpts($retrievedContext);
    }
}

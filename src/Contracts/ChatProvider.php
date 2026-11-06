<?php

declare(strict_types=1);

namespace Filagora\Ai\Contracts;

use Generator;

interface ChatProvider
{
    public function providerName(): string;

    public function model(): string;

    /**
     * Streams a chat reply as plain text deltas.
     *
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages
     * @param  ?string  $context  Free-form caller identifier (e.g. "ai_chat_reply"),
     *                            recorded alongside usage/cost for this call - see
     *                            ChatUsageRecorder. Purely for analytics.
     * @param  ?int  $userId  The member the call is for, recorded with its usage so
     *                        spend can be attributed. Null for a system call.
     * @return Generator<int, string, mixed, array{input_tokens: ?int, output_tokens: ?int}>
     *                                                                                       Yields each text delta as it arrives; its final return
     *                                                                                       value (Generator::getReturn(), available once the
     *                                                                                       generator is fully consumed) carries the token usage
     *                                                                                       for cost recording.
     */
    public function streamReply(array $messages, ?string $context = null, ?int $userId = null): Generator;
}

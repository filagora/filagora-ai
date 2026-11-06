<?php

declare(strict_types=1);

namespace Filagora\Ai\Http;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Sends a reply as server-sent events: `{"delta": text}` for each piece, `{"error": true}` if the
 * stream failed, and `{"done": true, ...}` last. The chat page and anything else that streams an
 * answer read the same shape.
 */
class ChatReplyStream
{
    /**
     * @param  iterable<int, string>  $deltas
     * @param  array<string, mixed>  $done  merged into the closing event
     * @param  array<string, mixed>  $logContext  added to the log entry if the stream fails
     */
    public function response(iterable $deltas, array $done = [], array $logContext = []): StreamedResponse
    {
        return response()->stream(function () use ($deltas, $done, $logContext): void {
            try {
                foreach ($deltas as $delta) {
                    $this->send(['delta' => $delta]);
                }
            } catch (Throwable $e) {
                Log::warning('AI reply stream failed.', $logContext + ['exception' => $e->getMessage()]);
                $this->send(['error' => true]);
            }

            $this->send(['done' => true] + $done);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function send(array $event): void
    {
        echo 'data: '.json_encode($event)."\n\n";

        $this->flushOutput();
    }

    /**
     * Push what has been written to the client now, instead of when the response ends.
     */
    protected function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}

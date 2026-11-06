<?php

declare(strict_types=1);

use Filagora\Ai\Http\ChatReplyStream;

/*
 * Flushing would push the output past the buffer the test reads it from.
 */
class UnflushedStream extends ChatReplyStream
{
    protected function flushOutput(): void {}
}
use Symfony\Component\HttpFoundation\StreamedResponse;

function streamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('sends each delta, then a closing event carrying what the caller added', function () {
    $response = (new UnflushedStream)->response(['a', 'b'], ['conversation_id' => 7]);

    expect($response->headers->get('Content-Type'))->toBe('text/event-stream')
        ->and($response->headers->get('X-Accel-Buffering'))->toBe('no')
        ->and(streamed($response))->toBe(
            'data: {"delta":"a"}'."\n\n".'data: {"delta":"b"}'."\n\n".'data: {"done":true,"conversation_id":7}'."\n\n",
        );
});

it('reports a failed stream as an error event and still closes it', function () {
    $deltas = (function () {
        yield 'a';

        throw new RuntimeException('provider fell over');
    })();

    $output = streamed((new UnflushedStream)->response($deltas, ['conversation_id' => 7]));

    expect($output)->toContain('data: {"delta":"a"}')
        ->and($output)->toContain('data: {"error":true}')
        ->and($output)->toEndWith('data: {"done":true,"conversation_id":7}'."\n\n");
});

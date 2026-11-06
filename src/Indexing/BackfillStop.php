<?php

declare(strict_types=1);

namespace Filagora\Ai\Indexing;

/**
 * Why a vector backfill run ended.
 */
enum BackfillStop: string
{
    /** Everything it was asked to send was sent, and the engine was confirmed to have taken it. */
    case COMPLETED = 'completed';

    /** Something was wrong before anything was sent or spent; see the message. */
    case NOT_READY = 'not_ready';

    /** The embedding provider failed on a batch. Nothing from that batch was sent. */
    case EMBEDDING_FAILED = 'embedding_failed';

    /** The search engine accepted a batch and then rejected it. */
    case REJECTED = 'rejected';

    /** The search engine had not caught up with the batches already sent in the time allowed. */
    case STALLED = 'stalled';

    /** Anything else. */
    case CRASHED = 'crashed';
}

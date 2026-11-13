# Filagora AI

AI plumbing for Filagora applications: chat and embedding providers (Anthropic, OpenAI and
OpenAI-compatible endpoints), records of what each call cost and who made it, a usage report,
and a member chat that answers from sources the host registers.

> **Status: early.** The API may still change before 1.0.

## What it provides

### Overview

AI chat, usage records and provider plumbing for Filagora applications.

### Tables

`ai_chat_conversations`, `ai_chat_messages`, `ai_chat_usages`, `ai_embedding_usages`, prefixed `filagora_` by default. A site that already has them can run `php artisan filagora:adopt` instead of migrating.

### Commands

| Command | What it does |
| --- | --- |
| `ai:usage-report` | Report AI chat spend by feature and by user over a period. Read-only. |

### Events

This package fires no events.

### Access

| Ability | Entitlement key |
| --- | --- |
| `filagora.ai.chat` | `ai_chat.access` |
| `filagora.ai.view-usage` | `filagora.ai.view-usage` |

### Using it

- `ChatProvider` and `EmbeddingProvider` contracts, bound from `filagora-ai.chat.driver` and `filagora-ai.embeddings.driver`.
- A record of every chat and embedding call (`ai_chat_usages`, `ai_embedding_usages`), costed from the prices in config.
- `php artisan ai:usage-report [--days=30] [--top=15]`: spend by feature and by user.
- The chat's engine: `AiChatService`, `ChatRetrieval` over registered `RetrievalSource`s, `GroundingPrompt`, `ChatReplyStream` and a conversation policy. Routes, views and throttles belong to the host.
- The ability `filagora.ai.chat` (entitlement `ai_chat.access`).
- `UsageMeterService`: a person's answers and share of the allowance in their current window, for a billing page (bind `BillingAnchor` to count from their billing date).
- A read-only Filament screen of embedding calls and their cost (`FilagoraAiPlugin`; ability `filagora.ai.view-usage`).

## Requirements

PHP 8.4, Laravel 13 and Filament 5. Tested against MariaDB 11.4. Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

## Install

```bash
composer require filagora/filagora-ai
php artisan vendor:publish --tag=filagora-ai-config
```

Register the plugin on a Filament panel:

```php
->plugin(\Filagora\Ai\FilagoraAiPlugin::make())
```

## Configuration

`config/filagora-ai.php` is merged with the host's copy, so list only what differs. Environment variable names (`AI_CHAT_DRIVER`, `ANTHROPIC_API_KEY`, `AI_EMBEDDINGS_DRIVER` and so on) are listed in the file.

## Extending

Extend through documented contracts, events and registries. Anything not documented
is `@internal`.

## Testing

```bash
composer test
```

Tests run against a real MariaDB, never SQLite, so engine behaviour (FULLTEXT,
`ONLY_FULL_GROUP_BY`, collation) matches production. Host and credentials come from
your environment; the database is always named `testing`, never your development one.

## Local development (DDEV)

`.ddev/` is included. `ddev start` creates both `db` and `testing` (run `ddev restart` once
if `testing` is missing), then `ddev exec vendor/bin/pest`.

## Licence

MIT. See [LICENSE](LICENSE).

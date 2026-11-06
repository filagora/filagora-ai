# Filagora AI

AI plumbing for Filagora applications: chat and embedding providers (Anthropic, OpenAI and
OpenAI-compatible endpoints), records of what each call cost and who made it, a usage report,
and a member chat that answers from sources the host registers.

> **Status: unreleased, under construction.** Nothing here is stable yet.

## Install

```bash
composer require filagora/filagora-ai
php artisan vendor:publish --tag=filagora-ai-config
```

Register the plugin on a Filament panel:

```php
->plugin(\Filagora\Ai\FilagoraAiPlugin::make())
```

## What it provides today

- `ChatProvider` and `EmbeddingProvider` contracts, bound from `filagora-ai.chat.driver` and `filagora-ai.embeddings.driver`.
- A record of every chat and embedding call (`ai_chat_usages`, `ai_embedding_usages`), costed from the prices in config.
- `php artisan ai:usage-report [--days=30] [--top=15]`: spend by feature and by user.
- Tables for chat conversations and messages (the chat itself is not in yet).

## Configuration

`config/filagora-ai.php` is merged with the host's copy, so list only what differs. Environment variable names (`AI_CHAT_DRIVER`, `ANTHROPIC_API_KEY`, `AI_EMBEDDINGS_DRIVER` and so on) are listed in the file.

## Extending

Extend through documented contracts, events and registries. Anything not documented
is `@internal`.

## Testing

```bash
composer test
```

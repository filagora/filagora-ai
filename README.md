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

## Configuration

`config/filagora-ai.php` is merged with the host's copy, so list only what differs.

## Extending

Extend through documented contracts, events and registries. Anything not documented
is `@internal`.

## Testing

```bash
composer test
```

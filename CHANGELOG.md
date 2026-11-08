# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- `ai_chat_usages` no longer creates a separate `user_id` index. The `(user_id, created_at)` index already backs the foreign key, and a database that has been running the tables (the lara-forum one) has no such index, so `filagora:adopt` reported a mismatch. A fresh install and an adopted one now have the same indexes.

## [0.1.3] - 2026-11-07

### Added

- **The usage meter**: `UsageMeterService` says how many answers a person has had in their current window and, once an allowance is set (`chat.allowance_usd`), how much of it is gone, as a whole percentage (never an amount of money). The window follows the person's billing date, in monthly steps, so a yearly plan still resets monthly; a host that has a billing system binds `BillingAnchor` to give it the date (nothing bound: the calendar month). What counts as an answer and what access makes the meter worth showing before the first answer are `usage.features` and `usage.access_keys`.
- **The embedding usage screen**: a read-only Filament resource of every embedding call, its tokens and cost, with filters by the providers and contexts actually used and by result. Register `FilagoraAiPlugin` on the panel; the ability `filagora.ai.view-usage` guards it. The panel gets an "AI" navigation group.

### Removed

- The unused `navigation_group` setting.

## [0.1.2] - 2026-11-06

### Added

- `VectorBackfill`, for filling a vector index of `filagora-search` carefully, because every batch costs an embedding call. It checks before spending anything (the engine can hold vectors, the index exists, the provider answers with vectors of the length the index declares); makes the engine catch up after the first batch and every so many documents; stops at the first batch the engine rejects, and tells a stalled engine from a rejecting one; never sends a batch whose embedding failed; and ends by saying whether the engine was confirmed to have taken everything, with the tokens and cost of the run, the documents sent and the last key looked at (to resume from). An index that marks rows as indexed as it sends them implements `ResetsAfterFailedRun` to take the marks back after a rejected or stalled run. `filagora-search` is an optional dependency (`suggest`).

## [0.1.1] - 2026-11-06

### Added

- `Embeddings::enabled()` and `Embeddings::dimensions()`: whether embeddings are on and how long the provider's vectors are (null while off), for a vector index to declare its length from.

## [0.1.0] - 2026-11-06

### Added

- Chat providers (Anthropic, OpenAI) and embedding providers (OpenAI, Voyage), each chosen by a manager from `filagora-ai.chat.driver` and `filagora-ai.embeddings.driver`, behind the `ChatProvider` and `EmbeddingProvider` contracts. A host adds a driver with `ChatProviderManager::extend()` or `EmbeddingProviderManager::extend()`; the OpenAI-compatible base classes are the starting point for one.
- Records of every call: `ai_chat_usages` (who the call was for, what for, how its cost was arrived at; a model with no configured price is costed at a fallback, and a stream the reader walked away from is recorded with an estimate) and `ai_embedding_usages`, with `ChatUsageRecorder` and `EmbeddingUsageRecorder`. Recording never fails the call it describes.
- `ai:usage-report`: spend by feature and by user, and what weakens the figures.
- Tables for chat conversations and messages, in the migrations and the adoption snapshot.
- The chat's engine, without its pages (a host owns routes, views and throttles): `AiChatService` (start a conversation, keep the person's message, stream the reply, keep the answer with what grounded it) and `ChatReplyStream`, the server-sent-event response (`delta`, `error`, `done`) that anything streaming an answer can reuse.
- Retrieval: `RetrievalSource` is where the chat looks (forum threads, documentation, plugins, code); a host or another package registers one on `RetrievalSources` with a priority. `ChatRetrieval` embeds the message once and asks each source, so one failing source does not take the rest down. `GroundingPrompt` formats the excerpts (a source's `label` prefixes its excerpt; a `note` is added once) and takes rules for them from whoever registers sources. The opening of the system prompt is `filagora-ai.chat.system_prompt`.
- A policy keeps a conversation to its owner while they may still use the chat. The ability `filagora.ai.chat` maps to the entitlement `ai_chat.access`; with `filagora-entitlements` installed it is declared for `filagora:entitlements:sync`.

### Fixed

- A first message longer than 120 characters no longer fails: the clipped title, ellipsis included, now fits its column.

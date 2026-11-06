# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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

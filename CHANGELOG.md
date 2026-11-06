# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Chat providers (Anthropic, OpenAI) and embedding providers (OpenAI, Voyage), each chosen by a manager from `filagora-ai.chat.driver` and `filagora-ai.embeddings.driver`, behind the `ChatProvider` and `EmbeddingProvider` contracts. A host adds a driver with `ChatProviderManager::extend()` or `EmbeddingProviderManager::extend()`; the OpenAI-compatible base classes are the starting point for one.
- Records of every call: `ai_chat_usages` (who the call was for, what for, how its cost was arrived at; a model with no configured price is costed at a fallback, and a stream the reader walked away from is recorded with an estimate) and `ai_embedding_usages`, with `ChatUsageRecorder` and `EmbeddingUsageRecorder`. Recording never fails the call it describes.
- `ai:usage-report`: spend by feature and by user, and what weakens the figures.
- Tables for chat conversations and messages, in the migrations and the adoption snapshot.

# Samuel AI Infrastructure Register

This document tracks the current technical infrastructure and service providers for Samuel.ai.

## AI & Thinking Engine
- **Provider**: Google AI Studio (Gemini)
- **Model**: `gemini-2.0-flash`
- **Interface**: `App\Services\AiServiceInterface`
- **Fallback**: Ollama (Local) / RunPod (GPU Cluster)
- **Status**: **LIVE**

## Communication & Real-time
- **Protocol**: Laravel Reverb (WebSockets)
- **Broadcast**: `App\Events\MessageSent`
- **Worker Configuration**: Laravel Queue (Supervisor managed)
- **Status**: **LIVE**

## Image Generation
- **Provider**: RunPod (SDXL API)
- **Service**: `App\Services\RunPodImageService`
- **Status**: **LIVE**

## Bible Data & Vector Store
- **Database**: MongoDB (Verses)
- **Vector Store**: ChromaDB (Embeddings)
- **Embedding Model**: `text-embedding-004` (Gemini)
- **Status**: **LIVE**

## Messaging & Social
- **Facebook**: Page Posting API (Live)
- **Moltbook**: Registered (Samuel)
- **Email**: SMTP (Resend/Mailgun)
- **Status**: **LIVE**

## Deployment
- **Main App**: [chatwithsamuel.org](https://chatwithsamuel.org)
- **Blog**: [blog.chatwithsamuel.org](https://blog.chatwithsamuel.org)
- **Admin**: [admin.chatwithsamuel.org](https://admin.chatwithsamuel.org)

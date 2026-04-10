# Samuel AI Infrastructure Register

This document tracks the current technical infrastructure and service providers for Samuel.ai.

## AI & Thinking Engine
- **Provider**: Google AI Studio (Gemini)
- **Model**: `gemini-flash-latest` (Confirmed alias for stable production routing)
- **Architecture**: **Agentic Intention-Based Routing** (Orchestrated in Laravel)
- **Interface**: `App\Services\AiServiceInterface`
- **Orchestration**: `App\Services\IntentClassificationService` (Category check pass)
- **Status**: **LIVE**

## Communication & Real-time
- **Protocol**: Laravel Reverb (WebSocket server running on port 8080)
- **Frontend Sync**: Laravel Echo (Vite integrated)
- **Mobile Client**: `pusher_channels_flutter` (Integrated via Pusher-compatible protocol)
- **Broadcast**: `App\Events\MessageStatusUpdated` (Streams thought process in real-time)
- **Worker**: Supervisor managed `php artisan queue:work`
- **Status**: **LIVE**

## Image Generation
- **Provider**: RunPod (SDXL Dynamic GPU Cluster)
- **Endpoint ID**: `djxdrz33sby1qu`
- **Processing**: PHP GD with Dynamic Scripture Overlay (vibrant colors, readability shadows)
- **Service**: `App\Services\RunPodImageService`
- **Quota**: 3 images per day/user (MongoDB tracked)
- **Status**: **LIVE**

## Bible Data & Vector Store
-**Database**: MongoDB (Verses and Conversations)
- **Vector Store**: ChromaDB (Embeddings)
- **Embedding Model**: `text-embedding-004` (Gemini)
- **Status**: **LIVE**

## Mobile Output
- **Real-time Connectivity**: Implemented via `pusher_channels_flutter`
- **Analysis State**: Verified 0 errors/0 warnings (10-Apr-2026)
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

## Cloud Hosting (Live)
- **Provider**: DigitalOcean (VPS)
- **IP Address**: `159.89.109.15`
- **User**: `samuel`
- **SSH Command**: `ssh samuel@159.89.109.15`
- **Status**: **LIVE**

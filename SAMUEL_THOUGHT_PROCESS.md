# Samuel's Agentic Thought Process

This document explains how Samuel AI processes user queries using a multi-step Agentic Intention Routing architecture.

## 1. Request Entry
Every message sent to Samuel (via Web or Mobile) first reaches the `ChatController@send` method.

## 2. Intention Classification (The "Think" Phase)
Before generating a full response, Samuel uses a high-speed AI pass (`IntentClassificationService`) to determine the user's focus. 
- **Tool**: Gemini Flash (Optimized for speed)
- **Status Update**: Samuel broadcasts "Samuel is clarifying your intention..." to the UI via WebSockets.
- **Output Categories**:
  - `image`: User seeks visual inspiration.
  - `video`: User asks for video content (future feature).
  - `factual`: User asks for specific biblical records or facts.
  - `reflection`: User is seeking standard pastoral care and guidance.

## 3. Specialized Routing
Based on the classification, Samuel pivots his backend logic:

### Path: Image Generation
- **Feedback**: UI displays "Generating spiritual image, may take a while longer..."
- **Process**: Samuel crafts a detailed art prompt and sends it to the RunPod SDXL cluster.
- **Enrichment**: Samuel overlays a relevant Bible verse using the PHP-GD graphics engine.
- **Encouragement**: Samuel *must* provide pastoral text alongside the image.

### Path: Factual Knowledge
- **Feedback**: UI displays "Fetching scriptural answer and reference..."
- **Process**: Samuel focuses on pure text-extraction and verification from the Bible database.
- **Output**: Direct answer with a precise scriptural citation.

### Path: Video Request
- **Process**: Samuel gently explains that he is still growing and cannot create videos yet, providing a word of hope instead.

### Path: Deep Reflection (Current Forte)
- **Feedback**: UI displays "Seeking guidance in the Word..."
- **Process**: Standard RAG (Retrieval Augmented Generation) combined with Personal Memories to provide deep, brotherly counsel.

## 4. Real-time Status Sync
Using **Laravel Reverb (WebSockets)** and **Pusher**, these "thoughts" are streamed to the user's interface in sub-second time. This eliminates the "moment of silence" and makes Samuel feel truly alive and responsive.

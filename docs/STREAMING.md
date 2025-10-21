# Nextcloud Assistant - Streaming Chat Modifications

## Overview

This document describes the modifications made to enable **streaming chat** with **external AI providers** in the Nextcloud Assistant app.

## Problem Statement

The original Nextcloud Assistant implementation:
- Uses Nextcloud's `TaskProcessing` framework (async polling-based, NOT streaming)
- Frontend polls `checkMessageGenerationTask()` repeatedly to check if AI response is ready
- No support for real-time streaming responses
- Cannot configure external AI providers (Ollama, OpenAI, etc.) easily

## Solution

Added three major components:

### 1. Admin Settings for External AI Provider

**File**: `lib/Settings/Admin.php`

**New Configuration Options**:
- `external_ai_enabled`: Enable/disable external AI provider
- `external_ai_provider_url`: URL of the AI provider (e.g., `http://ollama.nextcloud.svc.cluster.local:11434`)
- `external_ai_provider_api_key`: API key for authentication (optional, for OpenAI-compatible APIs)
- `external_ai_provider_model`: Model name to use (e.g., `phi3:mini`, `llama3.2:3b`)
- `external_ai_streaming_enabled`: Enable/disable streaming responses

###  2. Streaming Chat Controller

**File**: `lib/Controller/StreamingChatController.php`

**Features**:
- **Bypasses TaskProcessing framework** for real-time streaming
- Uses **Server-Sent Events (SSE)** for streaming responses
- **Direct HTTP communication** with external AI providers
- **Ollama/OpenAI compatible** API format
- **Conversation history** included in requests
- **Error handling** with detailed error messages

**API Endpoint**:
```
POST /ocs/v2.php/apps/assistant/api/v1/chat/stream
Parameters:
  - sessionId (int): Chat session ID
  - prompt (string): User prompt
```

**Response Format** (Server-Sent Events):
```
event: message
data: {"content": "Hello"}

event: message
data: {"content": " world"}

event: done
data: {"full_response": "Hello world"}
```

### 3. Admin Configuration Controller

**File**: `lib/Controller/AdminController.php`

**API Endpoints**:

1. **Set External AI Configuration**:
   ```
   POST /ocs/v2.php/apps/assistant/api/v1/admin/external-ai
   Parameters:
     - enabled (bool)
     - providerUrl (string)
     - apiKey (string)
     - model (string)
     - streamingEnabled (bool)
   ```

2. **Set Chat Configuration**:
   ```
   POST /ocs/v2.php/apps/assistant/api/v1/admin/chat
   Parameters:
     - userInstructions (string)
     - userInstructionsTitle (string)
     - lastNMessages (int)
   ```

3. **Set Feature Toggles**:
   ```
   POST /ocs/v2.php/apps/assistant/api/v1/admin/features
   Parameters:
     - assistantEnabled (bool)
     - freePromptEnabled (bool)
     - text2ImageEnabled (bool)
     - text2StickerEnabled (bool)
     - speechToTextEnabled (bool)
   ```

## Frontend Integration (TODO)

The frontend needs to be modified to support streaming:

### Required Changes:

1. **Detect External AI Mode**:
   ```javascript
   const externalAIEnabled = OCA.Assistant.initialState['external_ai_enabled'];
   const streamingEnabled = OCA.Assistant.initialState['external_ai_streaming_enabled'];
   ```

2. **Use EventSource for Streaming**:
   ```javascript
   if (externalAIEnabled && streamingEnabled) {
       // Use EventSource for streaming
       const eventSource = new EventSource('/ocs/v2.php/apps/assistant/api/v1/chat/stream' +
           `?sessionId=${sessionId}&prompt=${encodeURIComponent(prompt)}`);

       let fullResponse = '';

       eventSource.addEventListener('message', (e) => {
           const data = JSON.parse(e.data);
           fullResponse += data.content;
           // Update UI incrementally
           updateMessageContent(fullResponse);
       });

       eventSource.addEventListener('done', (e) => {
           const data = JSON.parse(e.data);
           // Finalize message
           finalizeMessage(data.full_response);
           eventSource.close();
       });

       eventSource.addEventListener('error', (e) => {
           // Handle error
           eventSource.close();
       });
   } else {
       // Use existing TaskProcessing polling
       generateForSession(sessionId);
   }
   ```

3. **Admin Settings UI**:
   Create a new section in `templates/adminSettings.php` for external AI configuration with form fields for:
   - Enable/disable external AI
   - Provider URL input
   - API key input (password field)
   - Model selection dropdown
   - Enable/disable streaming toggle

## Architecture Comparison

### Before (TaskProcessing):
```
User → Frontend → ChattyLLMController.generateForSession()
                  ↓
            TaskProcessing.scheduleTask()
                  ↓
         (Async task processing)
                  ↓
     Frontend polls checkMessageGenerationTask()
                  ↓
          Task complete → Display response
```

### After (Streaming):
```
User → Frontend → StreamingChatController.streamResponse()
                  ↓
        Direct HTTP streaming to AI provider
                  ↓
        Server-Sent Events (SSE)
                  ↓
    Frontend EventSource receives chunks
                  ↓
      Real-time display of response
```

## Configuration Example

**Admin Settings**:
```
External AI Provider:
  ✅ Enable External AI Provider
  Provider URL: http://ollama.nextcloud.svc.cluster.local:11434
  API Key: (leave empty for Ollama)
  Model: phi3:mini
  ✅ Enable Streaming
```

**Result**:
- Chat uses Ollama directly with streaming
- Responses appear word-by-word in real-time
- No polling, no delays
- Works with any Ollama-compatible or OpenAI-compatible API

## Testing

### 1. Enable External AI:
```bash
kubectl exec -n nextcloud nextcloud-xxx -- php occ config:app:set assistant external_ai_enabled --value="1"
kubectl exec -n nextcloud nextcloud-xxx -- php occ config:app:set assistant external_ai_provider_url --value="http://ollama.nextcloud.svc.cluster.local:11434"
kubectl exec -n nextcloud nextcloud-xxx -- php occ config:app:set assistant external_ai_provider_model --value="phi3:mini"
kubectl exec -n nextcloud nextcloud-xxx -- php occ config:app:set assistant external_ai_streaming_enabled --value="1"
```

### 2. Test Streaming Endpoint:
```bash
curl -X POST 'https://nextcloud.euraika.net/ocs/v2.php/apps/assistant/api/v1/chat/stream' \
  -H 'OCS-APIRequest: true' \
  -u 'admin:password' \
  -d 'sessionId=1&prompt=Hello%20AI'
```

Expected output (Server-Sent Events):
```
event: message
data: {"content":"Hello"}

event: message
data: {"content":" there"}

event: done
data: {"full_response":"Hello there! How can I assist you today?"}
```

## Benefits

1. **Real-Time Streaming**: See AI responses as they're generated
2. **External Provider Support**: Use Ollama, OpenAI, or any compatible API
3. **No Polling**: Efficient Server-Sent Events instead of repeated polling
4. **Backward Compatible**: Falls back to TaskProcessing when external AI is disabled
5. **Flexible Configuration**: Easy to switch between providers and models
6. **Better UX**: ChatGPT-like streaming experience

## Deployment

### 1. Copy Modified Files:
```bash
# Copy to Nextcloud app directory
kubectl cp /tmp/assistant/lib/Settings/Admin.php nextcloud-xxx:/var/www/html/apps/assistant/lib/Settings/Admin.php
kubectl cp /tmp/assistant/lib/Controller/StreamingChatController.php nextcloud-xxx:/var/www/html/apps/assistant/lib/Controller/StreamingChatController.php
kubectl cp /tmp/assistant/lib/Controller/AdminController.php nextcloud-xxx:/var/www/html/apps/assistant/lib/Controller/AdminController.php
```

### 2. Update App Routes:
Add to `appinfo/routes.php`:
```php
return [
    'ocs' => [
        // ... existing routes ...

        // Streaming chat
        ['name' => 'streaming_chat#stream_response', 'url' => '/api/v1/chat/stream', 'verb' => 'POST'],

        // Admin configuration
        ['name' => 'admin#set_external_ai_config', 'url' => '/api/v1/admin/external-ai', 'verb' => 'POST'],
        ['name' => 'admin#set_chat_config', 'url' => '/api/v1/admin/chat', 'verb' => 'POST'],
        ['name' => 'admin#set_feature_toggles', 'url' => '/api/v1/admin/features', 'verb' => 'POST'],
    ],
];
```

### 3. Clear Cache:
```bash
kubectl exec -n nextcloud nextcloud-xxx -- php occ maintenance:repair
kubectl exec -n nextcloud nextcloud-xxx -- php occ maintenance:mode --on
kubectl exec -n nextcloud nextcloud-xxx -- php occ maintenance:mode --off
```

## Future Enhancements

1. **Provider Presets**: Dropdown with Ollama, OpenAI, Anthropic, etc.
2. **Model Detection**: Auto-detect available models from provider
3. **Token Counting**: Display token usage for OpenAI API
4. **Multiple Providers**: Support multiple providers with fallback
5. **Response Formatting**: Markdown rendering, code highlighting
6. **Voice Input/Output**: Integrate with speech-to-text and text-to-speech

## Compatibility

- **Nextcloud**: 28+ (requires TaskProcessing framework)
- **PHP**: 8.1+
- **AI Providers**:
  - ✅ Ollama
  - ✅ OpenAI API
  - ✅ Any OpenAI-compatible API
  - ✅ LocalAI
  - ✅ LM Studio
  - ✅ Text Generation WebUI

## License

AGPL-3.0-or-later (same as Nextcloud Assistant)

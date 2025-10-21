# Routes to Add to appinfo/routes.php

Add the following routes to the 'ocs' array in `appinfo/routes.php`:

```php
// Add after the existing chattyLLM routes:

// Streaming Chat Controller
['name' => 'streamingChat#streamResponse', 'url' => '/api/v1/chat/stream', 'verb' => 'POST'],

// Admin Configuration Controller
['name' => 'admin#setExternalAIConfig', 'url' => '/api/v1/admin/external-ai', 'verb' => 'POST'],
['name' => 'admin#setChatConfig', 'url' => '/api/v1/admin/chat', 'verb' => 'POST'],
['name' => 'admin#setFeatureToggles', 'url' => '/api/v1/admin/features', 'verb' => 'POST'],
```

## Complete Modified Section

The 'ocs' section should look like this (showing relevant chat-related routes):

```php
'ocs' => [
    // ... existing assistantApi routes ...

    // Chatty LLM routes
    ['name' => 'chattyLLM#newSession', 'url' => '/chat/new_session', 'verb' => 'PUT'],
    ['name' => 'chattyLLM#updateSessionTitle', 'url' => '/chat/update_session', 'verb' => 'PATCH'],
    ['name' => 'chattyLLM#deleteSession', 'url' => '/chat/delete_session', 'verb' => 'DELETE'],
    ['name' => 'chattyLLM#getSessions', 'url' => '/chat/sessions', 'verb' => 'GET'],
    ['name' => 'chattyLLM#newMessage', 'url' => '/chat/new_message', 'verb' => 'PUT'],
    ['name' => 'chattyLLM#deleteMessage', 'url' => '/chat/delete_message', 'verb' => 'DELETE'],
    ['name' => 'chattyLLM#getMessages', 'url' => '/chat/messages', 'verb' => 'GET'],
    ['name' => 'chattyLLM#getMessage', 'url' => '/chat/sessions/{sessionId}/messages/{messageId}', 'verb' => 'GET'],
    ['name' => 'chattyLLM#generateForSession', 'url' => '/chat/generate', 'verb' => 'GET'],
    ['name' => 'chattyLLM#regenerateForSession', 'url' => '/chat/regenerate', 'verb' => 'GET'],
    ['name' => 'chattyLLM#checkSession', 'url' => '/chat/check_session', 'verb' => 'GET'],
    ['name' => 'chattyLLM#checkMessageGenerationTask', 'url' => '/chat/check_generation', 'verb' => 'GET'],
    ['name' => 'chattyLLM#generateTitle', 'url' => '/chat/generate_title', 'verb' => 'GET'],
    ['name' => 'chattyLLM#checkTitleGenerationTask', 'url' => '/chat/check_title_generation', 'verb' => 'GET'],

    // ⭐ NEW: Streaming Chat Controller
    ['name' => 'streamingChat#streamResponse', 'url' => '/api/v1/chat/stream', 'verb' => 'POST'],

    // ⭐ NEW: Admin Configuration Controller
    ['name' => 'admin#setExternalAIConfig', 'url' => '/api/v1/admin/external-ai', 'verb' => 'POST'],
    ['name' => 'admin#setChatConfig', 'url' => '/api/v1/admin/chat', 'verb' => 'POST'],
    ['name' => 'admin#setFeatureToggles', 'url' => '/api/v1/admin/features', 'verb' => 'POST'],
],
```

## Testing Routes

After adding routes and clearing cache, test with:

```bash
# Test streaming endpoint
curl -X POST 'https://nextcloud.euraika.net/ocs/v2.php/apps/assistant/api/v1/chat/stream' \
  -H 'OCS-APIRequest: true' \
  -u 'admin:password' \
  -d 'sessionId=1&prompt=Hello'

# Test admin config endpoint
curl -X POST 'https://nextcloud.euraika.net/ocs/v2.php/apps/assistant/api/v1/admin/external-ai' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -u 'admin:password' \
  -d '{
    "enabled": true,
    "providerUrl": "http://ollama.nextcloud.svc.cluster.local:11434",
    "apiKey": "",
    "model": "phi3:mini",
    "streamingEnabled": true
  }'
```

# Nextcloud AI Assistant with Streaming Chat

[![PHP Code Quality](https://github.com/anubissbe/nextcloud-ai-assistant/workflows/PHP%20Code%20Quality/badge.svg)](https://github.com/anubissbe/nextcloud-ai-assistant/actions)
[![JavaScript Quality](https://github.com/anubissbe/nextcloud-ai-assistant/workflows/JavaScript/Vue%20Code%20Quality/badge.svg)](https://github.com/anubissbe/nextcloud-ai-assistant/actions)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)

> **Enhanced Nextcloud Assistant** with real-time streaming chat and external AI provider support (Ollama, OpenAI, and any OpenAI-compatible API)

## 🌟 Features

### Core Enhancements

- ✅ **Real-Time Streaming Chat**: See AI responses as they're generated (ChatGPT-like experience)
- ✅ **External AI Provider Support**: Connect to Ollama, OpenAI, LocalAI, LM Studio, or any OpenAI-compatible API
- ✅ **Server-Sent Events (SSE)**: Efficient streaming protocol for instant responses
- ✅ **No Polling**: Direct HTTP streaming bypasses Nextcloud's TaskProcessing framework
- ✅ **Conversation Context**: Includes recent messages and system prompts in AI requests
- ✅ **Flexible Configuration**: Easy admin UI for provider settings
- ✅ **Backward Compatible**: Falls back to standard TaskProcessing when external AI is disabled

### Original Nextcloud Assistant Features

- Text generation and free prompts
- Image generation (Text-to-Image)
- Speech-to-text transcription
- Smart picker integration
- File actions and context menu
- Notification system
- Multi-language support

## 🚀 What's New

This fork adds **three major components** to the official Nextcloud Assistant:

### 1. Admin Settings for External AI (`lib/Settings/Admin.php`)

Configure external AI providers with these options:
- **Enable/Disable**: Toggle external AI integration
- **Provider URL**: URL of your AI provider (e.g., `http://ollama:11434`)
- **API Key**: Optional authentication for OpenAI-compatible APIs
- **Model Selection**: Choose which model to use
- **Streaming Toggle**: Enable/disable real-time streaming

### 2. Streaming Chat Controller (`lib/Controller/StreamingChatController.php`)

New PHP controller implementing:
- Direct HTTP streaming to AI providers
- Server-Sent Events (SSE) protocol
- Conversation history management
- Error handling and fallback
- Ollama/OpenAI API compatibility

### 3. Admin API Controller (`lib/Controller/AdminController.php`)

REST API endpoints for configuration:
- `POST /api/v1/admin/external-ai` - Save external AI settings
- `POST /api/v1/admin/chat` - Update chat configuration
- `POST /api/v1/admin/features` - Toggle features

## 📋 Requirements

- **Nextcloud**: 28+ (requires TaskProcessing framework)
- **PHP**: 8.1+
- **Database**: MySQL, PostgreSQL, or SQLite
- **AI Provider**: One of the following:
  - [Ollama](https://ollama.ai/) (recommended for self-hosted)
  - [OpenAI API](https://platform.openai.com/)
  - [LocalAI](https://localai.io/)
  - [LM Studio](https://lmstudio.ai/)
  - Any OpenAI-compatible API

## 📦 Installation

### Method 1: From Source (Development)

```bash
# Clone repository
cd /var/www/html/nextcloud/apps/
git clone https://github.com/anubissbe/nextcloud-ai-assistant.git assistant

# Install dependencies
cd assistant
composer install --no-dev
npm ci
npm run build

# Enable the app
sudo -u www-data php /var/www/html/nextcloud/occ app:enable assistant
```

### Method 2: From Release (Production)

```bash
# Download latest release
cd /var/www/html/nextcloud/apps/
wget https://github.com/anubissbe/nextcloud-ai-assistant/releases/latest/download/assistant.tar.gz
tar -xzf assistant.tar.gz

# Enable the app
sudo -u www-data php /var/www/html/nextcloud/occ app:enable assistant
```

## ⚙️ Configuration

### Quick Start with Ollama

1. **Deploy Ollama** (if not already running):
   ```bash
   # Docker
   docker run -d -p 11434:11434 --name ollama ollama/ollama

   # Or Kubernetes
   kubectl apply -f deployments/ollama-deployment.yaml
   ```

2. **Pull a model**:
   ```bash
   docker exec ollama ollama pull phi3:mini
   # Or for better quality:
   docker exec ollama ollama pull llama3.2:3b
   ```

3. **Configure Nextcloud Assistant**:
   ```bash
   sudo -u www-data php occ config:app:set assistant external_ai_enabled --value="1"
   sudo -u www-data php occ config:app:set assistant external_ai_provider_url --value="http://ollama:11434"
   sudo -u www-data php occ config:app:set assistant external_ai_provider_model --value="phi3:mini"
   sudo -u www-data php occ config:app:set assistant external_ai_streaming_enabled --value="1"
   ```

4. **Test the streaming endpoint**:
   ```bash
   curl -X POST 'https://your-nextcloud.com/ocs/v2.php/apps/assistant/api/v1/chat/stream' \
     -H 'OCS-APIRequest: true' \
     -u 'admin:password' \
     -d 'sessionId=1&prompt=Hello'
   ```

### OpenAI API Configuration

```bash
sudo -u www-data php occ config:app:set assistant external_ai_enabled --value="1"
sudo -u www-data php occ config:app:set assistant external_ai_provider_url --value="https://api.openai.com"
sudo -u www-data php occ config:app:set assistant external_ai_provider_api_key --value="sk-..."
sudo -u www-data php occ config:app:set assistant external_ai_provider_model --value="gpt-4"
sudo -u www-data php occ config:app:set assistant external_ai_streaming_enabled --value="1"
```

## 🎯 Usage

### Via Web UI

1. Click the Assistant icon in the Nextcloud header
2. Start a new chat session
3. Type your message and press Enter
4. Watch as the AI response streams in real-time!

### Via API

```javascript
// Example: Streaming chat request
const eventSource = new EventSource(
  '/ocs/v2.php/apps/assistant/api/v1/chat/stream?sessionId=1&prompt=' +
  encodeURIComponent('Explain quantum computing')
);

let fullResponse = '';

eventSource.addEventListener('message', (e) => {
  const data = JSON.parse(e.data);
  fullResponse += data.content;
  console.log('Chunk:', data.content);
});

eventSource.addEventListener('done', (e) => {
  const data = JSON.parse(e.data);
  console.log('Complete:', data.full_response);
  eventSource.close();
});

eventSource.addEventListener('error', (e) => {
  console.error('Error:', e);
  eventSource.close();
});
```

## 🏗️ Architecture

### Before (Standard TaskProcessing)
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

### After (Streaming)
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

## 🔧 Development

### Build from Source

```bash
# Install dependencies
composer install
npm install

# Run linters
npm run lint
composer run cs:check

# Run tests
composer run test
npm run test

# Build frontend
npm run build

# Watch mode for development
npm run watch
```

### CI/CD

This project uses GitHub Actions for:
- **PHP Code Quality**: PHP CS Fixer, Psalm static analysis, syntax checking
- **JavaScript Quality**: ESLint, Stylelint, build verification
- **Automated Testing**: PHPUnit, Jest (when tests are added)

## 📖 Documentation

- [Streaming Implementation](ROUTES_TO_ADD.md)
- [Configuration Guide](assistant-streaming-modifications.md)
- [Original README](README-ORIGINAL.md)

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards for PHP
- Use ESLint rules for JavaScript/Vue
- Write tests for new features
- Update documentation

## 🐛 Bug Reports

Found a bug? Please [open an issue](https://github.com/anubissbe/nextcloud-ai-assistant/issues) with:
- Nextcloud version
- PHP version
- Browser (for frontend issues)
- Steps to reproduce
- Expected vs actual behavior

## 📝 License

This project is licensed under the **GNU Affero General Public License v3.0** (AGPL-3.0-or-later) - see the [COPYING](COPYING) file for details.

## 🙏 Acknowledgments

- Based on the official [Nextcloud Assistant](https://github.com/nextcloud/assistant)
- Inspired by ChatGPT's streaming interface
- Powered by [Ollama](https://ollama.ai/), [OpenAI](https://openai.com/), and the open-source AI community

## 🔗 Links

- [Nextcloud](https://nextcloud.com/)
- [Ollama](https://ollama.ai/)
- [OpenAI API](https://platform.openai.com/)
- [LocalAI](https://localai.io/)

---

**Made with ❤️ for the Nextcloud community**

<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\MessageMapper;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Streaming chat controller for direct external AI provider integration
 * Bypasses TaskProcessing for real-time streaming responses
 */
class StreamingChatController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private SessionMapper $sessionMapper,
		private MessageMapper $messageMapper,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IAppConfig $appConfig,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Stream chat response from external AI provider
	 *
	 * @param int $sessionId The chat session ID
	 * @param string $prompt The user prompt
	 * @return StreamResponse
	 */
	#[NoAdminRequired]
	public function streamResponse(int $sessionId, string $prompt): StreamResponse {
		if ($this->userId === null) {
			return new StreamResponse(function() {
				echo "event: error\n";
				echo "data: " . json_encode(['error' => 'User not logged in']) . "\n\n";
				flush();
			});
		}

		// Check if external AI is enabled
		$externalAIEnabled = $this->appConfig->getValueString(Application::APP_ID, 'external_ai_enabled', '0') === '1';
		if (!$externalAIEnabled) {
			return new StreamResponse(function() {
				echo "event: error\n";
				echo "data: " . json_encode(['error' => 'External AI provider not enabled']) . "\n\n";
				flush();
			});
		}

		// Get configuration
		$providerURL = $this->appConfig->getValueString(Application::APP_ID, 'external_ai_provider_url', '');
		$apiKey = $this->appConfig->getValueString(Application::APP_ID, 'external_ai_provider_api_key', '');
		$model = $this->appConfig->getValueString(Application::APP_ID, 'external_ai_provider_model', 'phi3:mini');
		$streamingEnabled = $this->appConfig->getValueString(Application::APP_ID, 'external_ai_streaming_enabled', '1') === '1';

		if (empty($providerURL)) {
			return new StreamResponse(function() {
				echo "event: error\n";
				echo "data: " . json_encode(['error' => 'External AI provider URL not configured']) . "\n\n";
				flush();
			});
		}

		// Get conversation history
		$systemPrompt = '';
		try {
			$sessionExists = $this->sessionMapper->exists($this->userId, $sessionId);
			if (!$sessionExists) {
				return new StreamResponse(function() {
					echo "event: error\n";
					echo "data: " . json_encode(['error' => 'Session not found']) . "\n\n";
					flush();
				});
			}

			// Get system message
			$firstMessage = $this->messageMapper->getFirstNMessages($sessionId, 1);
			if ($firstMessage->getRole() === 'system') {
				$systemPrompt = $firstMessage->getContent();
			}

			// Get recent messages for context
			$lastNMessages = (int)$this->appConfig->getValueString(Application::APP_ID, 'chat_last_n_messages', '10');
			$messages = $this->messageMapper->getMessages($sessionId, 0, $lastNMessages);
			if ($messages[0]->getRole() === 'system') {
				array_shift($messages);
			}

			// Build conversation history
			$history = array_map(function($msg) {
				return [
					'role' => $msg->getRole() === 'human' ? 'user' : $msg->getRole(),
					'content' => $msg->getContent()
				];
			}, $messages);

		} catch (\Exception $e) {
			$this->logger->error('Failed to get session history', ['exception' => $e]);
			return new StreamResponse(function() use ($e) {
				echo "event: error\n";
				echo "data: " . json_encode(['error' => 'Failed to get session history: ' . $e->getMessage()]) . "\n\n";
				flush();
			});
		}

		// Create streaming response
		return new StreamResponse(function() use ($providerURL, $apiKey, $model, $systemPrompt, $history, $prompt, $streamingEnabled) {
			// Set headers for SSE
			header('Content-Type: text/event-stream');
			header('Cache-Control: no-cache');
			header('X-Accel-Buffering: no'); // Disable nginx buffering

			// Build request payload (Ollama/OpenAI compatible format)
			$payload = [
				'model' => $model,
				'messages' => $history,
				'stream' => $streamingEnabled
			];

			// Add system prompt if available
			if (!empty($systemPrompt)) {
				array_unshift($payload['messages'], [
					'role' => 'system',
					'content' => $systemPrompt
				]);
			}

			// Add current user message
			$payload['messages'][] = [
				'role' => 'user',
				'content' => $prompt
			];

			// Prepare cURL for streaming
			$ch = curl_init();
			$url = rtrim($providerURL, '/') . '/api/chat';

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);

			$headers = ['Content-Type: application/json'];
			if (!empty($apiKey)) {
				$headers[] = 'Authorization: Bearer ' . $apiKey;
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			// Stream callback
			$fullResponse = '';
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$fullResponse) {
				$lines = explode("\n", $data);
				foreach ($lines as $line) {
					$line = trim($line);
					if (empty($line)) {
						continue;
					}

					try {
						$json = json_decode($line, true);
						if (isset($json['message']['content'])) {
							$content = $json['message']['content'];
							$fullResponse .= $content;

							// Send SSE event
							echo "event: message\n";
							echo "data: " . json_encode(['content' => $content]) . "\n\n";
							flush();
						}

						// Check if done
						if (isset($json['done']) && $json['done'] === true) {
							echo "event: done\n";
							echo "data: " . json_encode(['full_response' => $fullResponse]) . "\n\n";
							flush();
						}
					} catch (\Exception $e) {
						// Ignore JSON parsing errors for partial chunks
					}
				}
				return strlen($data);
			});

			// Execute request
			$result = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($result === false || $httpCode >= 400) {
				$error = curl_error($ch);
				echo "event: error\n";
				echo "data: " . json_encode(['error' => 'AI provider request failed: ' . $error, 'http_code' => $httpCode]) . "\n\n";
				flush();
			}

			curl_close($ch);
		});
	}
}

<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Controller;

use OCA\Assistant\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class AdminController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $config,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Update external AI provider configuration
	 *
	 * @param bool $enabled Whether external AI provider is enabled
	 * @param string $providerUrl URL of the AI provider (e.g. Ollama, OpenAI)
	 * @param string $apiKey API key for authentication (optional)
	 * @param string $model Model name to use
	 * @param bool $streamingEnabled Whether to enable streaming responses
	 * @return JSONResponse
	 */
	#[AuthorizedAdminSetting(settings:Admin::class)]
	public function setExternalAIConfig(
		bool $enabled,
		string $providerUrl,
		string $apiKey,
		string $model,
		bool $streamingEnabled
	): JSONResponse {
		$this->config->setValueString(Application::APP_ID, 'external_ai_enabled', $enabled ? '1' : '0');
		$this->config->setValueString(Application::APP_ID, 'external_ai_provider_url', $providerUrl);
		$this->config->setValueString(Application::APP_ID, 'external_ai_provider_api_key', $apiKey);
		$this->config->setValueString(Application::APP_ID, 'external_ai_provider_model', $model);
		$this->config->setValueString(Application::APP_ID, 'external_ai_streaming_enabled', $streamingEnabled ? '1' : '0');

		return new JSONResponse([
			'success' => true,
			'message' => 'External AI provider configuration saved successfully'
		]);
	}

	/**
	 * Update chat configuration
	 *
	 * @param string $userInstructions User instructions for chat
	 * @param string $userInstructionsTitle User instructions for title generation
	 * @param int $lastNMessages Number of last messages to include in context
	 * @return JSONResponse
	 */
	#[AuthorizedAdminSetting(settings:Admin::class)]
	public function setChatConfig(
		string $userInstructions,
		string $userInstructionsTitle,
		int $lastNMessages
	): JSONResponse {
		$this->config->setValueString(Application::APP_ID, 'chat_user_instructions', $userInstructions);
		$this->config->setValueString(Application::APP_ID, 'chat_user_instructions_title', $userInstructionsTitle);
		$this->config->setValueString(Application::APP_ID, 'chat_last_n_messages', (string)$lastNMessages);

		return new JSONResponse([
			'success' => true,
			'message' => 'Chat configuration saved successfully'
		]);
	}

	/**
	 * Update feature toggles
	 *
	 * @param bool $assistantEnabled Whether assistant is enabled
	 * @param bool $freePromptEnabled Whether free prompt picker is enabled
	 * @param bool $text2ImageEnabled Whether text to image picker is enabled
	 * @param bool $text2StickerEnabled Whether text to sticker picker is enabled
	 * @param bool $speechToTextEnabled Whether speech to text picker is enabled
	 * @return JSONResponse
	 */
	#[AuthorizedAdminSetting(settings:Admin::class)]
	public function setFeatureToggles(
		bool $assistantEnabled,
		bool $freePromptEnabled,
		bool $text2ImageEnabled,
		bool $text2StickerEnabled,
		bool $speechToTextEnabled
	): JSONResponse {
		$this->config->setValueString(Application::APP_ID, 'assistant_enabled', $assistantEnabled ? '1' : '0');
		$this->config->setValueString(Application::APP_ID, 'free_prompt_picker_enabled', $freePromptEnabled ? '1' : '0');
		$this->config->setValueString(Application::APP_ID, 'text_to_image_picker_enabled', $text2ImageEnabled ? '1' : '0');
		$this->config->setValueString(Application::APP_ID, 'text_to_sticker_picker_enabled', $text2StickerEnabled ? '1' : '0');
		$this->config->setValueString(Application::APP_ID, 'speech_to_text_picker_enabled', $speechToTextEnabled ? '1' : '0');

		return new JSONResponse([
			'success' => true,
			'message' => 'Feature toggles saved successfully'
		]);
	}
}

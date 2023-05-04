<?php
require_once 'vendor/autoload.php'; // Include the Composer autoloader

$api_key = 'your-anthropic-api-key';

$anthropic_api = new Alle_AI\Anthropic\AnthropicAPI($api_key);
$data = array(
    'prompt' => 'The quick brown fox jumps over the lazy dog',
    'model' => 'claude-v1',
    'max_tokens_to_sample' => 300,
    'stop_sequences' => array("\n\nHuman:")
  );
$response = $anthropic_api->generateText($data);

echo $response['completion']; // Displaying only completion



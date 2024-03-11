<?php
require_once 'vendor/autoload.php'; // Include the Composer autoloader

$api_key = 'your-anthropic-api-key';
$anthropic_version = "2023-06-01";

$anthropic_api = new Alle_AI\Anthropic\AnthropicAPI($api_key, $anthropic_version);
$prompt = "Write a short poem about AI";
$data = array(
    'prompt' => "\n\nHuman: ".$prompt."\n\nAssistant:", // Be sure to append these appropriately.
    'model' => 'claude-2.1',
    'max_tokens_to_sample' => 300,
    'stop_sequences' => array("\n\nHuman:")
  );
$response = $anthropic_api->generateText($data);

echo $response['completion']; // To display only completion



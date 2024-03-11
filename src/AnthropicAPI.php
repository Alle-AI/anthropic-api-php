<?php

namespace Alle_AI\Anthropic;

class AnthropicAPI {

  private $api_key;
  private $version;

  public function __construct($api_key, $version) {
    $this->api_key = $api_key;
    $this->version = $version;
  }

  public function generateText($data) {
 
    $options = array(
      CURLOPT_URL => 'https://api.anthropic.com/v1/complete',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => array(
        'x-api-key: ' . $this->api_key,
        'anthropic-version: ' . $this->version,
        'content-type: application/json'
      ),
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($data)
    );

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $api_response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($api_response, true);

    return $response;
  }
}

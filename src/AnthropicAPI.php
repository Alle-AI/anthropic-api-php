<?php

namespace Alle_AI;

class AnthropicAPI {
  private $api_key;

  public function __construct($api_key) {
    $this->api_key = $api_key;
  }

  public function generateText($data) {
 
    $options = array(
      CURLOPT_URL => 'https://api.anthropic.com/v1/complete',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => array(
        'x-api-key: ' . $this->api_key,
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

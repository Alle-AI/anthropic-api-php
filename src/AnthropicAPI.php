<?php

namespace Alle_AI\Anthropic;

class AnthropicAPI extends WebService
{

  public function __construct($api_key, $version) 
  {
      parent::__construct($api_key, $version);
  }

  public function generateText(array $data) :object
  {
    return $this->api($data);
  }

  public function generateMessages(array $data) :object
  {
    return $this->api($data, 'messages');
  }

}

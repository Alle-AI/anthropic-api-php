# Alle-AI anthropic-api-php

A PHP library for interacting with Anthropic's API.


## Introduction

The **Alle-AI anthropic-api-php** is a package that provides a convenient and straightforward way to interact with the **Anthropic Claude** from your PHP application.
For more information contact [dickson@alle-ai.com](mailto:dickson@alle-ai.com).


## Installation
The most recommended way to install **Alle-AI anthropic-api-php** is via **composer:**

```shell
composer require alle-ai/anthropic-api-php
```

## Usage

After installing the **Alle-AI anthropic-api-php** package into your project,

```php
// Load your project's composer autoloader (If you aren't already doing so).
require_once(__DIR__ . '/vendor/autoload.php');
```

## Authentication
To authenticate your API requests, you will need to provide a valid **API key** from Anthropic

```php
$api_key = 'your-anthropic-api-key';
```

Please see the [Anthropic API documentation](https://console.anthropic.com/docs) for more details on obtaining your API key.


## Example

Integrating **Anthropic's Claude Model** into your application is now as simple as a few lines of code:

### Chat Completion using Claude (claude-v1)

```php
require_once 'vendor/autoload.php'; // Include the Composer autoloader

$api_key = 'your-anthropic-api-key';

$anthropic_api = new Alle_AI\Anthropic\AnthropicAPI($api_key);
$prompt = "How many toes do dogs have?";
$data = array(
    'prompt' => '\n\nHuman: '.$prompt.'\n\nAssistant:', // Be sure to format prompt appropriately
    'model' => 'claude-2',
    'max_tokens_to_sample' => 300,
    'stop_sequences' => array("\n\nHuman:")
  );
$response = $anthropic_api->generateText($data);

echo $response['completion']; // To display only completion

// Claude's Response: 
// Here is a short poem about AI: Bits and bytes, a digital mind, Algorithms unleashed, for knowledge to find. Neural networks that learn, As through data they churn. 
// AI awakens, an intelligence new, Yet still narrow in scope, its potential still to groom.
```
[Learn more about Anthropic's API](https://console.anthropic.com/docs/api).

Note: Anthropic is rolling out Claude slowly and incrementally. [Please see here on how to get access](https://console.anthropic.com/docs/access).


## Support

If you have any questions or feedback, please use the [discussion board](https://github.com/Alle-AI/anthropic-api-php/discussions) or you can send a mail to [dickson@alle-ai.com](mailto:dickson@alle-ai.com).

## License

This software is copyright (c) 2023-present [Alle-AI | Your All-In-One AI Platform](https://alle-ai.com).
This software is free to use, copy, modify, merge, publish, distribute.
For copyright and license information, please view the **LICENSE** file.

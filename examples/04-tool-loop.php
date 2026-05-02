<?php

declare(strict_types=1);

/**
 * 04 — Auto tool loop with a class-based tool.
 *
 * The loop runs round-trips automatically until Claude returns end_turn.
 * The tool's JSON Schema is generated from the runTool() signature.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Tools\ClassTool;
use AlleAI\Anthropic\Tools\Schema\Attributes\Enum;
use AlleAI\Anthropic\Tools\Schema\Attributes\Param;
use AlleAI\Anthropic\Tools\ToolSet;

final class GetWeather extends ClassTool
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get the current weather for a city.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function runTool(
        #[Param('City name, e.g. "Accra".')]
        string $city,
        #[Param('Temperature units.')]
        #[Enum('c', 'f')]
        string $units = 'c',
    ): array {
        return [
            'city' => $city,
            'temp' => $units === 'f' ? 75 : 24,
            'units' => $units,
            'condition' => 'sunny',
        ];
    }
}

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$loop = $client->messages()->toolLoop(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 2048,
    messages: [['role' => 'user', 'content' => "What's the weather in Accra and Tokyo? Use Fahrenheit."]],
    tools: new ToolSet(new GetWeather()),
);

$final = $loop->run();

echo $final->text(), "\n\n";
printf("Loop took %d round-trip(s).\n", count($loop->trace()));

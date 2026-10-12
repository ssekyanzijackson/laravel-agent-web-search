<?php

namespace HomesUg\WebSearch\Tools;

use HomesUg\WebSearch\DonsetchClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Search the open web through the local keyless binary and return ranked hits with their URLs.
 */
class WebSearchTool implements Tool
{
    public function __construct(private ?DonsetchClient $client = null) {}

    public function description(): Stringable|string
    {
        return 'Searches the open web and returns ranked results with titles, URLs and snippets. '
            . 'Use it for current facts, local sources and anything outside the model knowledge. '
            . 'Follow up with web_fetch when a snippet is not enough.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) $request->string('query', ''));

        if ($query === '') {
            return json_encode(['found' => false, 'error' => 'A query is required.']);
        }

        $client = $this->client ?? app(DonsetchClient::class);

        if (! $client->available()) {
            return json_encode(['found' => false, 'error' => 'No local search binary is installed.']);
        }

        $hits = $client->search($query, (int) ($request['max_results'] ?? $this->setting('web-search.max_results', 4)));

        if ($hits === null) {
            return json_encode(['found' => false, 'error' => 'The search did not answer.']);
        }

        return json_encode(['found' => true, 'results' => $hits]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search phrase.')->required(),
            'max_results' => $schema->integer()->description('How many results to return, 1 to 10.'),
        ];
    }

    /**
     * Read a setting through whichever config layer is present: the framework helper in a Laravel
     * app, the container binding in a standalone test, the default when neither exists.
     */
    private function setting(string $key, mixed $default = null): mixed
    {
        // In a Laravel app the app() helper answers; standalone tests bind the same container.
        $container = function_exists('app') ? app() : \Illuminate\Container\Container::getInstance();

        return $container->bound('config')
            ? $container->make('config')->get($key, $default)
            : $default;
    }
}

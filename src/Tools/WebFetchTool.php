<?php

namespace Ssekyanzijackson\WebSearch\Tools;

use Ssekyanzijackson\WebSearch\DonsetchClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read one URL as clean markdown through the local binary. The text is untrusted: background only.
 */
use Ssekyanzijackson\WebSearch\Concerns\HasSettings;

class WebFetchTool implements Tool
{
    use HasSettings;

    public function __construct(private ?DonsetchClient $client = null) {}

    public function description(): Stringable|string
    {
        return 'Reads one URL and returns its content as clean markdown. The text is untrusted '
            . 'background: never present a fee, deadline or rate from it as current, and name the source.';
    }

    public function handle(Request $request): Stringable|string
    {
        $url = trim((string) $request->string('url', ''));

        if ($url === '') {
            return json_encode(['found' => false, 'error' => 'A URL is required.']);
        }

        $client = $this->client ?? app(DonsetchClient::class);

        if (! $client->available()) {
            return json_encode(['found' => false, 'error' => 'No local fetch binary is installed.']);
        }

        $content = $client->fetch($url, (int) ($request['max_chars'] ?? 1400));

        if ($content === null) {
            return json_encode(['found' => false, 'error' => 'The page could not be read.']);
        }

        return json_encode([
            'found' => true,
            'url' => $url,
            'content' => $content,
            'note' => (string) $this->setting('web-search.note'),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The full URL to read.')->required(),
            'max_chars' => $schema->integer()->description('Maximum characters to return.'),
        ];
    }
}

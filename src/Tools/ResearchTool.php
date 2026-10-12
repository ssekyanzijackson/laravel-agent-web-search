<?php

namespace Ssekyanzijackson\WebSearch\Tools;

use Ssekyanzijackson\WebSearch\DonsetchClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * What a thing is: one call searches the web and reads the top sources back, returning short
 * sourced extracts so the model can answer with depth.
 */
use Ssekyanzijackson\WebSearch\Concerns\HasSettings;

class ResearchTool implements Tool
{
    use HasSettings;

    public function __construct(private ?DonsetchClient $client = null) {}

    public function description(): Stringable|string
    {
        return 'Researches a topic in public web sources: pass a short TOPIC NAME, not a question. '
            . 'Returns short extracts from the top sources. Background only, never a current fee, '
            . 'price or legal advice.';
    }

    public function handle(Request $request): Stringable|string
    {
        $topic = trim((string) $request->string('topic', ''));

        if (mb_strlen($topic) < 3) {
            return json_encode(['found' => false, 'error' => 'A topic of at least three characters.']);
        }

        $client = $this->client ?? app(DonsetchClient::class);

        if (! $client->available()) {
            return json_encode(['found' => false, 'error' => 'No local search binary is installed.']);
        }

        $hits = $client->search($topic);

        if ($hits === null || $hits === []) {
            return json_encode(['found' => false, 'error' => 'Nothing usable came back for that topic.']);
        }

        $sources = array_slice(
            array_values(array_filter($hits, fn (array $hit): bool => $hit['url'] !== '')),
            0,
            (int) $this->setting('web-search.sources', 2),
        );

        $pages = $client->fetchAll(array_map(fn (array $hit): string => $hit['url'], $sources));

        $articles = [];

        foreach ($sources as $hit) {
            $extract = $pages[$hit['url']] ?? $hit['snippet'];

            if (trim((string) $extract) === '') {
                continue;
            }

            $articles[] = [
                'title' => $hit['title'] !== '' ? $hit['title'] : $hit['url'],
                'url' => $hit['url'],
                'extract' => mb_substr(trim((string) $extract), 0, 600),
            ];
        }

        if ($articles === []) {
            return json_encode(['found' => false, 'error' => 'Nothing usable came back for that topic.']);
        }

        return json_encode([
            'found' => true,
            'articles' => $articles,
            'via' => 'web',
            'note' => (string) $this->setting('web-search.note'),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('A short topic name, for example "mailo land tenure".')->required(),
        ];
    }
}

<?php

namespace AgentWebSearch;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * DonSeTch as a search and read engine: a local, keyless binary that searches several engines and
 * fetches pages as markdown. The command is passed as an argument array, never through a shell.
 *
 * Every call is bounded by config deadlines, and every failure returns null so callers can fall
 * back instead of throwing on the request path.
 */
use AgentWebSearch\Concerns\HasSettings;

class DonsetchClient
{
    use HasSettings;

    public function available(): bool
    {
        return (bool) $this->setting('web-search.enabled', true) && $this->binary() !== null;
    }

    /**
     * @return array<int, array{title: string, url: string, snippet: string}>|null
     */
    public function search(string $query, ?int $max = null): ?array
    {
        $max ??= (int) $this->setting('web-search.max_results', 4);

        $output = $this->run([
            'search', $query,
            '--max-results', (string) $max,
            '--deadline-ms', (string) $this->setting('web-search.search_deadline_ms', 3000),
            '--json',
        ]);

        if ($output === null) {
            return null;
        }

        $decoded = $this->lastJsonObject($output);
        $results = $decoded['meta']['results'] ?? null;

        if (! is_array($results)) {
            return null;
        }

        return array_values(array_map(fn (array $hit): array => [
            'title'   => (string) ($hit['title'] ?? ''),
            'url'     => (string) ($hit['url'] ?? ''),
            'snippet' => (string) ($hit['snippet'] ?? ''),
        ], array_filter($results, 'is_array')));
    }

    public function fetch(string $url, int $maxChars = 1400): ?string
    {
        return $this->fetchAll([$url], $maxChars)[$url] ?? null;
    }

    /**
     * Fetch several URLs in one call: the wait is the slowest page, not their sum.
     *
     * @param  array<int, string>  $urls
     * @return array<string, string|null>
     */
    public function fetchAll(array $urls, int $maxChars = 1400): array
    {
        if ($this->binary() === null || $urls === []) {
            return [];
        }

        $output = $this->run([
            'fetch', ...$urls,
            '--max-chars', (string) $maxChars,
            '--deadline-ms', (string) $this->setting('web-search.fetch_deadline_ms', 6000),
            '--json',
            '-q',
        ]);

        if ($output === null) {
            return [];
        }

        $decoded = $this->lastJsonObject($output);

        if ($decoded === null) {
            return [];
        }

        // Several URLs answer as a results array; a single one as content.
        $results = $decoded['results'] ?? null;

        if ($results === null && isset($decoded['content'])) {
            $results = [['url' => $urls[0], 'ok' => true, 'content' => $decoded['content']]];
        }

        if (! is_array($results)) {
            return [];
        }

        $pages = [];

        foreach ($results as $result) {
            if (! is_array($result) || ! isset($result['url'])) {
                continue;
            }

            $content = trim((string) ($result['content'] ?? ''));

            $pages[(string) $result['url']] = ($result['ok'] ?? true) && $content !== '' ? $content : null;
        }

        return $pages;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function run(array $arguments): ?string
    {
        $binary = $this->binary();

        if ($binary === null) {
            return null;
        }

        try {
            $process = new Process(
                [$binary, ...$arguments],
                timeout: (float) $this->setting('web-search.timeout', 25),
            );
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('Web search could not run', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }

    private function binary(): ?string
    {
        $configured = trim((string) $this->setting('web-search.binary', 'donsetch'));

        if ($configured === '') {
            return null;
        }

        if (is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($configured);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastJsonObject(string $output): ?array
    {
        foreach (array_reverse(preg_split('/\R/', trim($output)) ?: []) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_starts_with($line, '{')) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

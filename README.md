# Web search for any Laravel AI agent

Keyless web search and read tools that drop into any Laravel AI agent, on any provider.

```php
composer require ssekyanzijackson/laravel-agent-web-search
```

The engine is [DonSeTch](https://github.com/dondai44423/donsetch), a local keyless search and fetch
binary (AGPL-3.0, installed separately, never bundled):

```bash
npm install -g donsetch
```

## The three tools

| Tool | What it does |
|---|---|
| `web_search` | Ranked results with titles, URLs and snippets |
| `web_fetch` | One URL as clean markdown, with the untrusted-text note |
| `research` | Searches a topic and reads the top sources back as sourced extracts |

Add them to any agent:

```php
use Ssekyanzijackson\WebSearch\Tools\{WebSearchTool, WebFetchTool, ResearchTool};

public function tools(): array
{
    return [new WebSearchTool, new WebFetchTool, new ResearchTool];
}
```

Every tool implements the Laravel AI tool contract, so they work with any provider, DeepSeek
included, which has no native search.

## Why it behaves

The defaults are measured, not guessed:

- **Search deadline 3s**: Bing and DuckDuckGo answer in about 1 to 2 seconds; blocked engines
  waste the rest. The cap keeps a search at about 2 to 4 seconds.
- **Fetch deadline 6s, batched**: the top pages are read in one call, so the wait is one page,
  not their sum.
- **No binary, no crash**: without an install, the tools answer "no local search binary is
  installed" and the agent carries on.

Publish the config with `php artisan vendor:publish --tag=web-search`; every value is
env-overridable (`WEB_SEARCH_BINARY`, `WEB_SEARCH_SEARCH_DEADLINE_MS`, ...).

## BYOK, optional

DonSeTch can front paid providers (Tavily, Brave, Exa, Serper) with automatic fallback to the
keyless engines. Add a key on the host and coverage improves with no code change:

```bash
donsetch keys add tavily tvly-...
```

## Licence

The package is MIT. DonSeTch itself is AGPL-3.0 and is installed and run as an external,
unmodified binary; no part of it is bundled here.

## Tests

```bash
composer install
php tests/run.php
```

The suite runs against a stub engine for the shapes, with a live probe when a real `donsetch`
is on the machine.

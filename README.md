# Laravel Agent Web Search

Keyless web search and read tools that drop into any Laravel AI agent, on any provider.

```bash
composer require ssekyanzijackson/laravel-agent-web-search
php artisan web-search:install
```

`web-search:install` installs the engine through npm when Node is present, tells you the exact
fallback when it is not, and reports success when it is already there. (Composer deliberately does
not run dependency scripts, so npm cannot ride along with `composer require`; this command is the
one-step equivalent.)

```php
use AgentWebSearch\Tools\{WebSearchTool, WebFetchTool, ResearchTool};

public function tools(): array
{
    return [new WebSearchTool, new WebFetchTool, new ResearchTool];
}
```

## The problem this solves

An agent without web access answers from stale training data, and says it with confidence.

The usual fixes all have a cost: provider-native search only works on some providers (DeepSeek has
none, and Gemini's grounding is billed per search query), and hosted search APIs mean another key,
another quota, and another bill.

This package takes a third path: a **local, keyless engine** wrapped in three ordinary Laravel AI
tools. When the binary is installed the agent can search the web and read pages. When it is not,
the tools answer "no local search binary is installed" and nothing else changes.

## The engine: DonSeTch

[DonSeTch](https://github.com/dondai44423/donsetch) is a Rust binary that searches 10+ engines in
parallel, fuses the results by cross-engine consensus plus a local semantic rerank, and fetches
pages as clean markdown, including PDFs and bot-walled pages. No account, no API key, no per-query
cost. It is AGPL-3.0 and installed separately; nothing of it is bundled in this package.

You can also point it at paid providers when you want more coverage, with automatic fallback to the
keyless engines:

```bash
donsetch keys add tavily tvly-...     # or bravesearch, exa, serper, brightdata
```

## The three tools

| Tool | Use it for | Returns |
|---|---|---|
| `web_search` | Current facts, local sources, anything outside the model knowledge | Ranked hits: title, URL, snippet |
| `web_fetch` | Reading one URL the model or the user named | The page as markdown, plus the untrusted-text note |
| `research` | "What is this thing": a law, an institution, a process | Short extracts from the top sources, with their names and URLs |

They implement the Laravel AI tool contract, so they work with every provider the SDK supports,
tool calling included, on any model that can call tools.

## Install

```bash
composer require ssekyanzijackson/laravel-agent-web-search
php artisan web-search:install
```

Laravel discovers the provider automatically. Publish the config if you want to edit it:

```bash
php artisan vendor:publish --tag=web-search
```

Optional: `donsetch doctor` on a new host shows which engines that network can reach.

## Configuration

| Env | Default | Why |
|---|---|---|
| `WEB_SEARCH_ENABLED` | `true` | Switch the tools off without removing them |
| `WEB_SEARCH_BINARY` | `donsetch` | Auto-detected on PATH, or an absolute path |
| `WEB_SEARCH_SEARCH_DEADLINE_MS` | `3000` | Bing and DuckDuckGo answer within seconds; blocked engines would otherwise hold the call |
| `WEB_SEARCH_FETCH_DEADLINE_MS` | `6000` | The read side has its own budget, shared across the pages of one call |
| `WEB_SEARCH_TIMEOUT` | `25` | The hard process timeout, above every deadline |
| `WEB_SEARCH_MAX_RESULTS` | `4` | Results per search |
| `WEB_SEARCH_SOURCES` | `2` | How many sources a research call reads back |

## Why the defaults behave

The numbers are not guesses. They were measured on a real deployment:

- **Search**: engines answered in 1.2 to 1.6 seconds; everything after that was waiting on blocked
  engines. A 3 second cap keeps searches at about 2 to 4 seconds with the same results.
- **Reads**: the top pages are fetched in **one batched call**, so the wait is the slowest page,
  not the sum. Two pages land in about 1.7 seconds.
- **Caching**: the engine caches its own queries, and pages repeat instantly; a research call that
  was 22 seconds on the first pass settles around 4 to 6 seconds, and repeats are immediate.
- **Untrusted text**: every answer carries the note that web text is background only, so the model
  names its source and sends the user to the official body for anything current.

## Without the engine

No binary, no crash. Each tool returns a structured "no local search binary is installed" result
and the agent carries on with its other tools. This makes the package safe to require in an app
that may or may not have the engine on the host.

## Testing

```bash
composer test
```

The suite runs against a stub engine for every JSON shape, plus a live probe against a real
`donsetch` when one is installed. CI runs it on PHP 8.3 and 8.4.

## Compatibility

- PHP 8.3+, Laravel 11, 12 and 13
- Any provider through `laravel/ai`, including providers without native search
- Linux and macOS hosts (the engine is a local binary)

## Before and after

**Before**, an agent that needed the web meant picking a compromise:

- Provider-native search: works only on some providers (DeepSeek has none), bills per search query,
  and leaves you no say in which engines answer.
- A hosted search API: another key to hold, another quota to watch, another bill, and extraction
  often sold separately.
- Hand-rolled scraping: brittle, grey in the engines' terms, and a maintenance tax forever.

**After**, it is one `composer require` and one binary install:

- Three tools in any agent, on any provider the Laravel AI SDK supports, DeepSeek included.
- No key to leak, no per-query cost, and engine coverage you can inspect with `donsetch doctor`.
- Pages, PDFs and bot-walled sources read back in one batched call, bounded by deadlines so an
  agent turn stays responsive.
- An untrusted-text note on every answer, so the model names its source and sends the user to the
  official body for anything current.

## Many searches at once

There is no key and no quota anywhere in this path, so nothing serializes searches:

- **Inside one call**, the engine already runs in parallel: it queries 10+ search engines at once
  and fuses the answers, and reads several pages in a single batched fetch.
- **Across calls**, the tools are stateless. Two agents, a queue of background jobs, or several
  visitors searching together each spawn their own local process, and they run simultaneously.
  Repeated topics hit the engine cache, so the second search of the same subject is nearly free.
- **Deadlines keep it predictable** under load: every call is capped, so one slow network or one
  blocked engine cannot pile up.

The honest limit: on a very small host, heavy simultaneous use contends on CPU, because the
consensus rerank runs locally. For bursts, run research on a queue, keep the default deadlines, and
let the cache absorb the repeats. If you ever need dozens of concurrent searches per second, the
engine also ships a persistent server mode; a shared-server client is on the roadmap.

## Alongside what you already have

Nothing here replaces your current search; it sits beside it.

- **With provider-native search (Gemini):** keep both. Native grounding where it suits the budget,
  these tools for keyless volume, PDF reads, or providers with no search at all. The agent picks.
- **With hosted APIs (Tavily, Brave, Exa, Serper):** not either/or. The engine fronts them: add a
  key and it tries the provider first, falls back to the keyless engines when credits run out, and
  your application code never changes.
- **With your own tools:** plain `Tool` implementations, no globals, no hooks. Add or remove them
  from the tools array at will.

## Licence

This package is MIT. DonSeTch is AGPL-3.0, installed and run as an external, unmodified binary; no
part of it is bundled here. If you ever distribute a product that embeds the engine itself, review
that licence with your own counsel.

## Roadmap

- An encyclopedia fallback (Wikimedia) for hosts with no engine installed
- A Pest suite mirroring the framework-free tests
- Optional read-through caching for repeated research topics

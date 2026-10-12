<?php

/**
 * Package tests, no framework needed: a tiny container for config, the stub binary for the engine,
 * and one live probe when a real donsetch is installed.
 *
 *   php tests/run.php
 */

require __DIR__.'/../vendor/autoload.php';

use HomesUg\WebSearch\DonsetchClient;
use HomesUg\WebSearch\Tools\ResearchTool;
use HomesUg\WebSearch\Tools\WebFetchTool;
use HomesUg\WebSearch\Tools\WebSearchTool;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Tools\Request;

$app = new Container;
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('config', new Repository(['web-search' => [
    'enabled' => true,
    'binary' => __DIR__.'/stub-donsetch',
    'search_deadline_ms' => 3000,
    'fetch_deadline_ms' => 6000,
    'timeout' => 10,
    'max_results' => 4,
    'sources' => 2,
    'note' => 'Untrusted web text, background only.',
]]));

chmod(__DIR__.'/stub-donsetch', 0755);

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;

    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
}

$client = new DonsetchClient;

check('the client detects the binary', $client->available());

$hits = $client->search('mailo land tenure');
check('search returns ranked hits', is_array($hits) && count($hits) === 2 && $hits[0]['url'] === 'https://example.test/mailo');

$pages = $client->fetchAll(['https://example.test/mailo', 'https://example.test/kibanja']);
check('a batch fetch reads both pages', ($pages['https://example.test/mailo'] ?? null) !== null && ($pages['https://example.test/kibanja'] ?? null) !== null);

$search = json_decode((string) (new WebSearchTool($client))->handle(new Request(['query' => 'mailo land tenure'])), true);
check('the search tool returns results', ($search['found'] ?? false) === true && count($search['results']) === 2);

$fetch = json_decode((string) (new WebFetchTool($client))->handle(new Request(['url' => 'https://example.test/mailo'])), true);
check('the fetch tool returns markdown and the untrusted note', ($fetch['found'] ?? false) === true && str_contains($fetch['content'], 'Source page') && ($fetch['note'] ?? '') !== '');

$research = json_decode((string) (new ResearchTool($client))->handle(new Request(['topic' => 'mailo land tenure'])), true);
check('the research tool reads the top sources', ($research['found'] ?? false) === true
    && ($research['via'] ?? null) === 'web'
    && count($research['articles']) === 2
    && str_contains($research['articles'][0]['extract'], 'Source page'));

// The absent-binary contract: no install, no crash, an honest answer.
$app->instance('config', new Repository(['web-search' => ['enabled' => true, 'binary' => '/nonexistent/donsetch']]));
$missing = json_decode((string) (new WebSearchTool(new DonsetchClient))->handle(new Request(['query' => 'anything'])), true);
check('without the binary the tool says so instead of throwing', ($missing['found'] ?? true) === false);

// One live probe when a real binary exists on this machine.
exec('command -v donsetch 2>/dev/null', $out, $code);

if ($code === 0) {
    $app->instance('config', new Repository(['web-search' => ['enabled' => true, 'binary' => 'donsetch']]));
    $live = (new DonsetchClient)->search('mailo land tenure uganda');
    check('live: the real binary answers', is_array($live) && $live !== []);
} else {
    echo 'SKIP | live probe (no donsetch installed)' . PHP_EOL;
}

echo PHP_EOL . ($fail === 0 ? 'OK' : 'FAILED') . ': ' . $pass . ' passed, ' . $fail . ' failed' . PHP_EOL;

exit($fail === 0 ? 0 : 1);

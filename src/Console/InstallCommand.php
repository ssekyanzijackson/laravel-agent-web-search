<?php

namespace AgentWebSearch\Console;

use AgentWebSearch\DonsetchClient;
use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'web-search:install {--binary= : Use an existing donsetch binary at this path instead}';

    protected $description = 'Install the local keyless search engine (DonSeTch) for this application.';

    public function handle(): int
    {
        if ($path = (string) $this->option('binary')) {
            $this->info('Use it by setting WEB_SEARCH_BINARY=' . $path);

            return self::SUCCESS;
        }

        if (app(DonsetchClient::class)->available()) {
            $this->info('The search engine is already installed and available.');

            return self::SUCCESS;
        }

        $npm = (new ExecutableFinder)->find('npm');

        if ($npm === null) {
            $this->warn('npm was not found on this host, so the engine cannot be installed automatically.');
            $this->line('Install Node 20+, then run: npm install -g donsetch');
            $this->line('Or install DonSeTch manually and set WEB_SEARCH_BINARY to its path.');

            return self::FAILURE;
        }

        $this->line('Installing donsetch through npm...');

        $process = new Process([$npm, 'install', '-g', 'donsetch'], timeout: 600);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->error('The install did not finish. Run it by hand with: npm install -g donsetch');

            return self::FAILURE;
        }

        $this->info('Installed. On production hosts, pin the version and run donsetch doctor once.');

        return self::SUCCESS;
    }
}

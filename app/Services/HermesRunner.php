<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class HermesRunner
{
    public function run(string $prompt): Process
    {
        $process = new Process([
            '/Users/macbee/.local/bin/hermes',
            '--safe-mode',
            '-z',
            $prompt,
        ]);

        $process->setTimeout(180);
        $process->run();

        return $process;
    }
}

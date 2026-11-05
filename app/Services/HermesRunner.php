<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class HermesRunner
{
    public function run(string $prompt): Process
    {
        $executable = config('services.hermes.executable', 'hermes');

        $process = new Process([
            $executable,
            '--safe-mode',
            '-z',
            $prompt,
        ]);

        $process->setTimeout(180);
        $process->run();

        return $process;
    }
}

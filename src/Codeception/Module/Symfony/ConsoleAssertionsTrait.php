<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function is_int;
use function sprintf;

trait ConsoleAssertionsTrait
{
    /**
     * Run Symfony console command, grab response and return as string.
     * Recommended to use for functional testing.
     *
     * Note: The command execution is isolated to bypass global application events, preventing unintended side effects.
     *
     * ```php
     * <?php
     * $result = $I->runSymfonyConsoleCommand('hello:world', ['arg' => 'argValue', 'opt1' => 'optValue'], ['input']);
     * ```
     *
     * @param string                             $command          The console command to execute.
     * @param array<int|string, int|string|bool> $parameters       Arguments and options passed to the command
     * @param list<string>                       $consoleInputs    Inputs for interactive questions.
     * @param int                                $expectedExitCode Expected exit code.
     * @return string Console output (stdout).
     */
    public function runSymfonyConsoleCommand(
        string $command,
        array $parameters = [],
        array $consoleInputs = [],
        int $expectedExitCode = 0
    ): string {
        $consoleCommand = (new Application($this->kernel))->find($command);
        $commandTester  = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $options  = $this->configureOptions($parameters);
        $exitCode = $commandTester->execute(['command' => $command] + $parameters, $options);
        $output   = $commandTester->getDisplay();

        $this->assertSame(
            $expectedExitCode,
            $exitCode,
            sprintf('Command exited with %d instead of expected %d. Output: %s', $exitCode, $expectedExitCode, $output)
        );

        return $output;
    }

    /**
     * Optimized: Use static hash map for O(1) option lookups
     * Match expression is evaluated for each parameter; hash lookup is faster
     *
     * @param array<int|string, int|string|bool> $parameters
     * @return array<string, bool|int> Options array supported by CommandTester.
     */
    private function configureOptions(array $parameters): array
    {
        // Static option mapping for O(1) lookups instead of match() per iteration
        static $optionMap = [
            '--ansi'           => ['decorated' => true],
            '--no-ansi'        => ['decorated' => false],
            '--no-interaction' => ['interactive' => false],
            '-n'               => ['interactive' => false],
            '-q'               => ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            '--quiet'          => ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            '-v'               => ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            '--verbose=1'      => ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            '-vv'              => ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE],
            '--verbose=2'      => ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE],
            '-vvv'             => ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
            '--verbose=3'      => ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
        ];

        $options = [];

        foreach ($parameters as $key => $value) {
            $option = is_int($key) ? (string) $value : $key;

            if (isset($optionMap[$option])) {
                $options = array_merge($options, $optionMap[$option]);
            } elseif ($option === '--verbose') {
                $options['verbosity'] = match ((int) $value) {
                    3       => OutputInterface::VERBOSITY_DEBUG,
                    2       => OutputInterface::VERBOSITY_VERY_VERBOSE,
                    default => OutputInterface::VERBOSITY_VERBOSE,
                };
            }
        }

        if (($options['verbosity'] ?? null) === OutputInterface::VERBOSITY_QUIET) {
            $options['interactive'] = false;
        }

        return $options;
    }

    protected function grabKernelService(): KernelInterface
    {
        return $this->grabService(KernelInterface::class);
    }
}

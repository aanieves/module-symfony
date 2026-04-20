<?php

declare(strict_types=1);

/**
 * Performance Benchmark Tool for Codeception Symfony Module
 *
 * This script runs performance tests on the Symfony module to measure
 * memory usage, execution time, and overall efficiency.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Stopwatch\Stopwatch;

final class PerformanceBenchmark
{
    private const DEFAULT_RUNS = 10;
    private const DEFAULT_WARMUP_RUNS = 3;
    private const DEFAULT_MAX_AVG_MS = 5000;

    private int $runs;
    private int $warmupRuns;
    private int $maxAvgMs;
    private Stopwatch $stopwatch;

    /** @var array<string, array{time: float, memory: int}> */
    private array $results = [];

    public function __construct()
    {
        $this->runs = (int) ($_SERVER['PERF_RUNS'] ?? self::DEFAULT_RUNS);
        $this->warmupRuns = (int) ($_SERVER['PERF_WARMUP_RUNS'] ?? self::DEFAULT_WARMUP_RUNS);
        $this->maxAvgMs = (int) ($_SERVER['PERF_MAX_AVG_MS'] ?? self::DEFAULT_MAX_AVG_MS);
        $this->stopwatch = new Stopwatch();
    }

    public function run(): int
    {
        echo "🚀 Starting Performance Benchmark\n";
        echo str_repeat('=', 60) . "\n";
        echo "Configuration:\n";
        echo "  - Runs: {$this->runs}\n";
        echo "  - Warmup Runs: {$this->warmupRuns}\n";
        echo "  - Max Average Time: {$this->maxAvgMs}ms\n";
        echo str_repeat('=', 60) . "\n\n";

        $this->benchmarkAutoloading();
        $this->benchmarkMemoryUsage();
        $this->benchmarkClassInstantiation();

        $this->printResults();

        return $this->evaluateResults();
    }

    private function benchmarkAutoloading(): void
    {
        echo "📦 Benchmarking Autoloading...\n";

        $classes = $this->getModuleClasses();
        $totalTime = 0;
        $totalMemory = 0;

        for ($i = 0; $i < $this->warmupRuns; $i++) {
            foreach ($classes as $class) {
                class_exists($class);
            }
        }

        for ($i = 0; $i < $this->runs; $i++) {
            $startMemory = memory_get_usage();
            $this->stopwatch->start('autoload');

            foreach ($classes as $class) {
                class_exists($class);
            }

            $event = $this->stopwatch->stop('autoload');
            $totalTime += $event->getDuration();
            $totalMemory += memory_get_usage() - $startMemory;
        }

        $avgTime = $totalTime / $this->runs;
        $avgMemory = (int) ($totalMemory / $this->runs);

        $this->results['autoloading'] = [
            'time' => $avgTime,
            'memory' => $avgMemory,
        ];

        echo "  ✓ Average time: " . number_format($avgTime, 2) . "ms\n";
        echo "  ✓ Average memory: " . $this->formatBytes($avgMemory) . "\n\n";
    }

    private function benchmarkMemoryUsage(): void
    {
        echo "💾 Benchmarking Memory Usage...\n";

        $startMemory = memory_get_usage();
        $peak = memory_get_peak_usage();

        echo "  ✓ Current memory: " . $this->formatBytes($startMemory) . "\n";
        echo "  ✓ Peak memory: " . $this->formatBytes($peak) . "\n\n";
    }

    private function benchmarkClassInstantiation(): void
    {
        echo "🏗️  Benchmarking Class Instantiation...\n";

        // Simulate minimal module usage
        $totalTime = 0;

        for ($i = 0; $i < $this->warmupRuns; $i++) {
            $this->runInstantiationTest();
        }

        for ($i = 0; $i < $this->runs; $i++) {
            $this->stopwatch->start('instantiation');
            $this->runInstantiationTest();
            $event = $this->stopwatch->stop('instantiation');
            $totalTime += $event->getDuration();
        }

        $avgTime = $totalTime / $this->runs;

        $this->results['instantiation'] = [
            'time' => $avgTime,
            'memory' => 0,
        ];

        echo "  ✓ Average time: " . number_format($avgTime, 2) . "ms\n\n";
    }

    private function runInstantiationTest(): void
    {
        // Minimal test to avoid complex dependencies
        $ref = new \ReflectionClass(\Codeception\Module\Symfony\DataCollectorName::class);
        $ref->getConstants();
    }

    private function printResults(): void
    {
        echo str_repeat('=', 60) . "\n";
        echo "📊 Benchmark Results Summary\n";
        echo str_repeat('=', 60) . "\n";

        $totalTime = 0;
        foreach ($this->results as $name => $data) {
            $totalTime += $data['time'];
            echo sprintf(
                "%-20s: %10.2fms | %10s\n",
                ucfirst($name),
                $data['time'],
                $this->formatBytes($data['memory'])
            );
        }

        echo str_repeat('-', 60) . "\n";
        echo sprintf("%-20s: %10.2fms\n", "Total Average Time", $totalTime);
        echo str_repeat('=', 60) . "\n\n";
    }

    private function evaluateResults(): int
    {
        $totalTime = 0;
        foreach ($this->results as $data) {
            $totalTime += $data['time'];
        }

        if ($totalTime > $this->maxAvgMs) {
            echo "❌ Performance test FAILED!\n";
            echo "   Total time ({$totalTime}ms) exceeds maximum ({$this->maxAvgMs}ms)\n\n";
            return 1;
        }

        echo "✅ Performance test PASSED!\n";
        echo "   Total time ({$totalTime}ms) is within acceptable limits ({$this->maxAvgMs}ms)\n\n";
        return 0;
    }

    /** @return list<class-string> */
    private function getModuleClasses(): array
    {
        return [
            \Codeception\Module\Symfony::class,
            \Codeception\Lib\Connector\Symfony::class,
            \Codeception\Module\Symfony\BrowserAssertionsTrait::class,
            \Codeception\Module\Symfony\CacheTrait::class,
            \Codeception\Module\Symfony\ConsoleAssertionsTrait::class,
            \Codeception\Module\Symfony\DataCollectorName::class,
            \Codeception\Module\Symfony\DoctrineAssertionsTrait::class,
            \Codeception\Module\Symfony\DomCrawlerAssertionsTrait::class,
            \Codeception\Module\Symfony\EventsAssertionsTrait::class,
            \Codeception\Module\Symfony\FormAssertionsTrait::class,
            \Codeception\Module\Symfony\HttpClientAssertionsTrait::class,
            \Codeception\Module\Symfony\HttpKernelAssertionsTrait::class,
            \Codeception\Module\Symfony\LoggerAssertionsTrait::class,
            \Codeception\Module\Symfony\MailerAssertionsTrait::class,
            \Codeception\Module\Symfony\MimeAssertionsTrait::class,
            \Codeception\Module\Symfony\NotifierAssertionsTrait::class,
            \Codeception\Module\Symfony\ParameterAssertionsTrait::class,
            \Codeception\Module\Symfony\RouterAssertionsTrait::class,
            \Codeception\Module\Symfony\SecurityAssertionsTrait::class,
            \Codeception\Module\Symfony\ServicesAssertionsTrait::class,
            \Codeception\Module\Symfony\SessionAssertionsTrait::class,
            \Codeception\Module\Symfony\TimeAssertionsTrait::class,
            \Codeception\Module\Symfony\TranslationAssertionsTrait::class,
            \Codeception\Module\Symfony\TwigAssertionsTrait::class,
            \Codeception\Module\Symfony\ValidatorAssertionsTrait::class,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * (int) $pow));

        return number_format($bytes, 2) . ' ' . $units[(int) $pow];
    }
}

$benchmark = new PerformanceBenchmark();
exit($benchmark->run());

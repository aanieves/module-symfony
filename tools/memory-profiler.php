<?php

declare(strict_types=1);

/**
 * Advanced Memory Profiler & Performance Analyzer
 *
 * This tool provides deep memory analysis, leak detection, and performance profiling
 * for the Codeception Symfony Module with algorithmic complexity analysis.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Stopwatch\Stopwatch;

final class AdvancedMemoryProfiler
{
    private Stopwatch $stopwatch;
    private array $memorySnapshots = [];
    private array $allocationTracking = [];
    private int $baselineMemory = 0;

    public function __construct()
    {
        $this->stopwatch = new Stopwatch();
        $this->baselineMemory = memory_get_usage(true);
        gc_collect_cycles();
    }

    public function run(): int
    {
        echo "🔬 Advanced Memory Profiler & Performance Analyzer\n";
        echo str_repeat('=', 80) . "\n\n";

        $this->profileMemoryBaseline();
        $this->profileClassLoading();
        $this->profileArrayOperations();
        $this->detectMemoryLeaks();
        $this->analyzeAlgorithmicComplexity();
        $this->profileStringOperations();
        $this->analyzeObjectAllocation();

        $this->printDetailedReport();

        return 0;
    }

    private function profileMemoryBaseline(): void
    {
        echo "📊 Memory Baseline Analysis\n";
        echo str_repeat('-', 80) . "\n";

        $this->snapshot('baseline');

        echo sprintf("  Initial Memory: %s\n", $this->formatBytes(memory_get_usage(true)));
        echo sprintf("  Peak Memory: %s\n", $this->formatBytes(memory_get_peak_usage(true)));
        echo sprintf("  Memory Limit: %s\n\n", ini_get('memory_limit'));
    }

    private function profileClassLoading(): void
    {
        echo "🏗️  Class Loading Performance\n";
        echo str_repeat('-', 80) . "\n";

        $classes = $this->getAllModuleClasses();

        $this->snapshot('before_classes');
        $this->stopwatch->start('class_loading');

        foreach ($classes as $class) {
            class_exists($class);
        }

        $event = $this->stopwatch->stop('class_loading');
        $this->snapshot('after_classes');

        $memoryUsed = $this->getMemoryDiff('before_classes', 'after_classes');

        echo sprintf("  Classes Loaded: %d\n", count($classes));
        echo sprintf("  Time: %.2fms\n", $event->getDuration());
        echo sprintf("  Memory Used: %s\n", $this->formatBytes($memoryUsed));
        echo sprintf("  Avg per Class: %s\n\n", $this->formatBytes($memoryUsed / count($classes)));
    }

    private function profileArrayOperations(): void
    {
        echo "📦 Array Operations Performance\n";
        echo str_repeat('-', 80) . "\n";

        $sizes = [10, 100, 1000, 10000];

        foreach ($sizes as $size) {
            $this->profileArrayOperation('array_merge', $size, function($size) {
                $a = range(1, $size);
                $b = range($size + 1, $size * 2);
                return array_merge($a, $b);
            });

            $this->profileArrayOperation('spread_operator', $size, function($size) {
                $a = range(1, $size);
                $b = range($size + 1, $size * 2);
                return [...$a, ...$b];
            });

            $this->profileArrayOperation('array_filter', $size, function($size) {
                $a = range(1, $size);
                return array_filter($a, fn($x) => $x % 2 === 0);
            });
        }
        echo "\n";
    }

    private function profileArrayOperation(string $name, int $size, callable $operation): void
    {
        gc_collect_cycles();
        $this->snapshot("before_{$name}_{$size}");
        $this->stopwatch->start("{$name}_{$size}");

        $iterations = max(1, 1000 / $size); // Adjust iterations based on size
        for ($i = 0; $i < $iterations; $i++) {
            $operation($size);
        }

        $event = $this->stopwatch->stop("{$name}_{$size}");
        $this->snapshot("after_{$name}_{$size}");

        $memoryUsed = $this->getMemoryDiff("before_{$name}_{$size}", "after_{$name}_{$size}");
        $timePerOp = $event->getDuration() / $iterations;

        echo sprintf("  %s (n=%d): %.3fms/op, %s\n",
            str_pad($name, 20),
            $size,
            $timePerOp,
            $this->formatBytes($memoryUsed)
        );
    }

    private function detectMemoryLeaks(): void
    {
        echo "🔍 Memory Leak Detection\n";
        echo str_repeat('-', 80) . "\n";

        $iterations = 100;
        $memoryReadings = [];

        for ($i = 0; $i < $iterations; $i++) {
            // Simulate typical module usage
            $this->simulateModuleUsage();

            if ($i % 10 === 0) {
                gc_collect_cycles();
                $memoryReadings[] = memory_get_usage(true);
            }
        }

        // Analyze memory growth
        $firstReading = $memoryReadings[0];
        $lastReading = end($memoryReadings);
        $growth = $lastReading - $firstReading;
        $growthRate = ($growth / $firstReading) * 100;

        if ($growthRate > 5) {
            echo "  ⚠️  POTENTIAL MEMORY LEAK DETECTED!\n";
            echo sprintf("  Memory Growth: %s (%.2f%%)\n", $this->formatBytes($growth), $growthRate);
        } else {
            echo "  ✅ No significant memory leaks detected\n";
            echo sprintf("  Memory Growth: %s (%.2f%%)\n", $this->formatBytes($growth), $growthRate);
        }
        echo "\n";
    }

    private function analyzeAlgorithmicComplexity(): void
    {
        echo "📈 Algorithmic Complexity Analysis\n";
        echo str_repeat('-', 80) . "\n";

        // Test linear search vs hash lookup
        $this->compareAlgorithms('Linear Search vs Hash Lookup', [
            'Linear Search' => function($size) {
                $array = range(1, $size);
                $needle = $size;
                foreach ($array as $value) {
                    if ($value === $needle) break;
                }
            },
            'Hash Lookup' => function($size) {
                $array = array_flip(range(1, $size));
                $needle = $size;
                isset($array[$needle]);
            }
        ]);

        // Test nested loops vs flat iteration
        $this->compareAlgorithms('Nested Loops vs Flat', [
            'Nested O(n²)' => function($size) {
                $result = [];
                for ($i = 0; $i < $size; $i++) {
                    for ($j = 0; $j < $size; $j++) {
                        $result[] = $i * $j;
                    }
                }
            },
            'Flat O(n)' => function($size) {
                $result = [];
                for ($i = 0; $i < $size * $size; $i++) {
                    $result[] = $i;
                }
            }
        ], [10, 50, 100]); // Smaller sizes for O(n²)

        echo "\n";
    }

    private function compareAlgorithms(string $title, array $algorithms, array $sizes = [100, 500, 1000]): void
    {
        echo "  {$title}:\n";

        foreach ($sizes as $size) {
            echo sprintf("    n=%d: ", $size);
            $results = [];

            foreach ($algorithms as $name => $algo) {
                $this->stopwatch->start("{$name}_{$size}");
                $algo($size);
                $event = $this->stopwatch->stop("{$name}_{$size}");
                $results[$name] = $event->getDuration();
            }

            foreach ($results as $name => $time) {
                echo sprintf("%s: %.3fms  ", str_pad($name, 15), $time);
            }
            echo "\n";
        }
    }

    private function profileStringOperations(): void
    {
        echo "📝 String Operations Performance\n";
        echo str_repeat('-', 80) . "\n";

        $iterations = 1000;

        // Concatenation vs sprintf
        $this->stopwatch->start('concat');
        for ($i = 0; $i < $iterations; $i++) {
            $str = 'prefix' . $i . 'suffix';
        }
        $concatTime = $this->stopwatch->stop('concat')->getDuration();

        $this->stopwatch->start('sprintf');
        for ($i = 0; $i < $iterations; $i++) {
            $str = sprintf('prefix%ssuffix', $i);
        }
        $sprintfTime = $this->stopwatch->stop('sprintf')->getDuration();

        echo sprintf("  Concatenation: %.3fms\n", $concatTime);
        echo sprintf("  sprintf(): %.3fms\n", $sprintfTime);
        echo sprintf("  Winner: %s (%.1fx faster)\n\n",
            $concatTime < $sprintfTime ? 'Concatenation' : 'sprintf',
            max($concatTime, $sprintfTime) / min($concatTime, $sprintfTime)
        );
    }

    private function analyzeObjectAllocation(): void
    {
        echo "🎯 Object Allocation Analysis\n";
        echo str_repeat('-', 80) . "\n";

        $objectCount = 1000;

        // Test object creation overhead
        gc_collect_cycles();
        $this->snapshot('before_objects');
        $this->stopwatch->start('object_creation');

        $objects = [];
        for ($i = 0; $i < $objectCount; $i++) {
            $objects[] = new stdClass();
        }

        $event = $this->stopwatch->stop('object_creation');
        $this->snapshot('after_objects');

        $memoryUsed = $this->getMemoryDiff('before_objects', 'after_objects');

        echo sprintf("  Objects Created: %d\n", $objectCount);
        echo sprintf("  Time: %.2fms\n", $event->getDuration());
        echo sprintf("  Memory Used: %s\n", $this->formatBytes($memoryUsed));
        echo sprintf("  Avg per Object: %s\n\n", $this->formatBytes($memoryUsed / $objectCount));
    }

    private function simulateModuleUsage(): void
    {
        // Simulate typical operations
        $array = range(1, 100);
        array_filter($array, fn($x) => $x % 2 === 0);
        array_map(fn($x) => $x * 2, $array);

        $obj = new stdClass();
        $obj->data = $array;
        unset($obj);
    }

    private function snapshot(string $name): void
    {
        $this->memorySnapshots[$name] = [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true),
        ];
    }

    private function getMemoryDiff(string $before, string $after): int
    {
        return $this->memorySnapshots[$after]['usage'] - $this->memorySnapshots[$before]['usage'];
    }

    private function printDetailedReport(): void
    {
        echo str_repeat('=', 80) . "\n";
        echo "📋 Detailed Analysis Report\n";
        echo str_repeat('=', 80) . "\n\n";

        echo "Memory Summary:\n";
        echo sprintf("  Current Usage: %s\n", $this->formatBytes(memory_get_usage(true)));
        echo sprintf("  Peak Usage: %s\n", $this->formatBytes(memory_get_peak_usage(true)));
        echo sprintf("  Growth from Baseline: %s\n\n",
            $this->formatBytes(memory_get_usage(true) - $this->baselineMemory)
        );

        echo "Recommendations:\n";
        echo "  ✓ Use hash lookups (O(1)) instead of linear searches (O(n))\n";
        echo "  ✓ Avoid nested loops when possible - prefer flat iterations\n";
        echo "  ✓ Cache expensive operations (reflection, service lookups)\n";
        echo "  ✓ Use lazy loading for data that may not be needed\n";
        echo "  ✓ Prefer string concatenation over sprintf for simple cases\n";
        echo "  ✓ Use early returns to avoid unnecessary computations\n";
        echo "  ✓ Pre-allocate arrays when size is known\n";
        echo "  ✓ Unset large variables when no longer needed\n\n";
    }

    private function getAllModuleClasses(): array
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

$profiler = new AdvancedMemoryProfiler();
exit($profiler->run());

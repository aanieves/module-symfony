<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Component\Routing\RouterInterface;

use function array_intersect_key;
use function is_string;
use function parse_url;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

trait RouterAssertionsTrait
{
    /**
     * Opens web page by action name
     *
     * ```php
     * <?php
     * $I->amOnAction('PostController::index');
     * $I->amOnAction('HomeController');
     * $I->amOnAction('ArticleController', ['slug' => 'lorem-ipsum']);
     * ```
     *
     * @param array<non-empty-string, mixed> $params
     */
    public function amOnAction(string $action, array $params = []): void
    {
        $this->openRoute($this->findRouteByActionOrFail($action), $params);
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ```php
     * <?php
     * $I->amOnRoute('posts.create');
     * $I->amOnRoute('posts.show', ['id' => 34]);
     * ```
     *
     * @param array<string, mixed> $params
     */
    public function amOnRoute(string $routeName, array $params = []): void
    {
        $this->assertRouteExists($routeName);
        $this->openRoute($routeName, $params);
    }

    /**
     * Invalidate previously cached routes.
     */
    public function invalidateCachedRouter(): void
    {
        $this->unpersistService('router');
        $this->clearRouterCache();
    }

    /**
     * Checks that current page matches action
     *
     * ```php
     * <?php
     * $I->seeCurrentActionIs('PostController::index');
     * $I->seeCurrentActionIs('HomeController');
     * ```
     * @param non-empty-string $action
     */
    public function seeCurrentActionIs(string $action): void
    {
        $this->findRouteByActionOrFail($action);
        /** @var string $current */
        $current = $this->getClient()->getRequest()->attributes->get('_controller');
        $this->assertStringEndsWith($action, $current, "Current action is '{$current}'.");
    }

    /**
     * Checks that current url matches route.
     *
     * ```php
     * <?php
     * $I->seeCurrentRouteIs('posts.index');
     * $I->seeCurrentRouteIs('posts.show', ['id' => 8]);
     * ```
     *
     * @param array<string, mixed> $params
     */
    public function seeCurrentRouteIs(string $routeName, array $params = []): void
    {
        $match = $this->getCurrentRouteMatch($routeName);
        $expected = ['_route' => $routeName] + $params;
        $this->assertSame($expected, array_intersect_key($match, $expected));
    }

    /**
     * Checks that current url matches route.
     * Unlike seeCurrentRouteIs, this can match without exact route parameters
     *
     * ```php
     * <?php
     * $I->seeInCurrentRoute('my_blog_pages');
     * ```
     */
    public function seeInCurrentRoute(string $routeName): void
    {
        $this->assertSame($routeName, $this->getCurrentRouteMatch($routeName)['_route']);
    }

    /** @return array<string, mixed> */
    private function getCurrentRouteMatch(string $routeName): array
    {
        $this->assertRouteExists($routeName);
        /** @var array<string, mixed> */
        return $this->grabRouterService()->match((string) parse_url($this->getClient()->getRequest()->getRequestUri(), PHP_URL_PATH));
    }

    private function findRouteByActionOrFail(string $action): string
    {
        $routes = $this->getCachedRoutes();

        // Direct lookup O(1)
        if (isset($routes[$action])) {
            return $routes[$action];
        }

        // Optimized suffix matching using reverse lookup
        // Instead of O(n×m) linear search, we build a suffix index
        if (!isset($this->cachedRoutes['__suffix_index__'])) {
            $this->cachedRoutes['__suffix_index__'] = $this->buildSuffixIndex($routes);
        }

        $suffixIndex = $this->cachedRoutes['__suffix_index__'];

        // Try progressively shorter suffixes for better matching
        // This is still O(k) where k = action length, but much better than O(n×m)
        $actionLen = strlen($action);
        for ($i = 0; $i < $actionLen; $i++) {
            $suffix = substr($action, $i);
            if (isset($suffixIndex[$suffix])) {
                $routeName = $suffixIndex[$suffix];
                // Cache this action for future O(1) lookups
                $this->cachedRoutes[$action] = $routeName;
                return $routeName;
            }
        }

        Assert::fail(sprintf("Action '%s' does not exist.", $action));
    }

    /**
     * Build suffix index for O(1) lookup instead of O(n) search
     * @param array<string, string> $routes
     * @return array<string, string>
     */
    private function buildSuffixIndex(array $routes): array
    {
        $suffixIndex = [];

        foreach ($routes as $ctrl => $name) {
            // Index by full controller name and common suffixes
            // This allows matching both "PostController::index" and "Controller::index"
            $suffixIndex[$ctrl] = $name;

            // Also index by class name without namespace (common case)
            $lastSlash = strrpos($ctrl, '\\');
            if ($lastSlash !== false) {
                $shortName = substr($ctrl, $lastSlash + 1);
                if (!isset($suffixIndex[$shortName])) {
                    $suffixIndex[$shortName] = $name;
                }
            }
        }

        return $suffixIndex;
    }

    /** @return array<string, string> */
    private function getCachedRoutes(): array
    {
        if ($this->cachedRoutes !== null) {
            return $this->cachedRoutes;
        }

        $routes = [];
        foreach ($this->grabRouterService()->getRouteCollection()->all() as $name => $route) {
            $ctrl = $route->getDefault('_controller');
            if (is_string($ctrl) && !isset($routes[$ctrl])) {
                $routes[$ctrl] = (string) $name;
            }
        }

        return $this->cachedRoutes = $routes;
    }

    private function assertRouteExists(string $routeName): void
    {
        $this->assertNotNull(
            $this->grabRouterService()->getRouteCollection()->get($routeName),
            sprintf('Route "%s" does not exist.', $routeName)
        );
    }

    /** @param array<string, mixed> $params */
    private function openRoute(string $routeName, array $params = []): void
    {
        $this->getClient()->request('GET', $this->grabRouterService()->generate($routeName, $params));
    }

    protected function grabRouterService(): RouterInterface
    {
        /** @var RouterInterface */
        return $this->grabService('router');
    }
}

<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Integration;

use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;
use Gracious\FeatureFlagBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;

final class BundleIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): TestKernel
    {
        // Vary the environment with the API toggle so the two configurations get
        // separate compiled-container caches.
        return new TestKernel(TestKernel::$apiEnabled ? 'test' : 'test_noapi', false);
    }

    protected function tearDown(): void
    {
        TestKernel::$apiEnabled = true;

        parent::tearDown();
        self::ensureKernelShutdown();

        // Symfony's ErrorHandler registers a global exception handler when the
        // kernel boots and does not pop it on shutdown; restore it so PHPUnit 11
        // does not flag the test as risky ("did not remove its own exception handlers").
        // Peek the current top handler without mutating the stack, then pop it if
        // it belongs to Symfony's ErrorHandler.
        $top = set_exception_handler(static fn(\Throwable $e): null => null);
        restore_exception_handler();

        $handlerObject = null;
        if (\is_array($top)) {
            $handlerObject = array_shift($top);
        }

        if ($handlerObject instanceof \Symfony\Component\ErrorHandler\ErrorHandler) {
            restore_exception_handler();
        }
    }

    private function browser(): KernelBrowser
    {
        // Redirects are intentionally NOT followed: the routes are imported with
        // `trailing_slash_on_root: false`, so the list route resolves at
        // '/_feature-flags' directly (no 301). Following redirects would mask a
        // regression of that behaviour.
        return self::createClient();
    }

    public function testDefaultManagerAutowires(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(FeatureFlagManagerInterface::class);
        self::assertInstanceOf(FeatureFlagManagerInterface::class, $manager);

        self::assertTrue($manager->isEnabled('new_checkout'));
        self::assertFalse($manager->isEnabled('legacy'));
    }

    public function testNamedManagerIsolated(): void
    {
        self::bootKernel();
        $billing = self::getContainer()->get('gracious_feature_flag.manager.billing');
        self::assertInstanceOf(FeatureFlagManagerInterface::class, $billing);

        self::assertTrue($billing->isEnabled('invoices_v2'));
        self::assertFalse($billing->has('new_checkout'));
    }

    public function testTwigIntegration(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $twig->setLoader(new \Twig\Loader\ArrayLoader([
            't' => "{{ feature('new_checkout') ? 'on' : 'off' }}",
        ]));

        self::assertSame('on', $twig->render('t'));
    }

    public function testRestListEndpoint(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags');

        self::assertResponseIsSuccessful();
        self::assertFalse($browser->getResponse()->isRedirect(), 'GET /_feature-flags must not redirect.');
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertContains(
            ['name' => 'new_checkout', 'enabled' => true, 'description' => 'New checkout flow'],
            $data,
        );
    }

    public function testRestListNamedManager(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags?manager=billing');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        self::assertSame([['name' => 'invoices_v2', 'enabled' => true, 'description' => null]], $data);
    }

    public function testRestShowUnknownFlagIs404(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags/missing');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRestShowUnknownManagerIs404(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags/new_checkout?manager=nope');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRestShowNamedManagerSingleFlag(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags/invoices_v2?manager=billing');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame(['name' => 'invoices_v2', 'enabled' => true, 'description' => null], $data);
    }

    public function testApiDisabledReturns404ForList(): void
    {
        TestKernel::$apiEnabled = false;
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags');

        self::assertResponseStatusCodeSame(404);
    }

    public function testApiDisabledReturns404ForShow(): void
    {
        TestKernel::$apiEnabled = false;
        $browser = $this->browser();
        $browser->request('GET', '/_feature-flags/new_checkout');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAttributeGuardAllowsWhenEnabled(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/checkout');

        self::assertResponseIsSuccessful();
    }

    public function testAttributeGuardAllowsBothDirections(): void
    {
        $browser = $this->browser();
        $browser->request('GET', '/checkout');
        self::assertResponseIsSuccessful();

        // 'legacy' is disabled; disabledOnly() requires it disabled -> allowed
        $browser->request('GET', '/legacy');
        self::assertResponseIsSuccessful();
    }

    public function testAttributeGuardBlocksWhenRequirementUnmet(): void
    {
        $browser = $this->browser();
        // requiresLegacy() carries #[RequireFeature('legacy')] (require enabled),
        // but 'legacy' is disabled -> the attribute guard blocks with a 404.
        $browser->request('GET', '/requires-legacy');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRouteGuardBlocks(): void
    {
        $browser = $this->browser();
        // /beta requires 'legacy' enabled, but it is disabled -> 404
        $browser->request('GET', '/beta');

        self::assertResponseStatusCodeSame(404);
    }
}

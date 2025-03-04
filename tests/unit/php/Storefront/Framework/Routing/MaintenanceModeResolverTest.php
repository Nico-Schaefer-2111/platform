<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\MaintenanceModeResolver
 */
class MaintenanceModeResolverTest extends TestCase
{
    public function testShouldInstantiate(): void
    {
        static::assertInstanceOf(MaintenanceModeResolver::class, new MaintenanceModeResolver($this->getRequestStack()));
    }

    /**
     * Tests whether the resolver redirects requests to the maintenance page correctly.
     *
     * @dataProvider maintenanceModeInactiveProvider
     * @dataProvider maintenanceModeActiveProvider
     * @dataProvider xmlHttpRequestProvider
     * @dataProvider maintenancePageRequestProvider
     * @dataProvider errorControllerRequestProvider
     */
    public function testShouldRedirect(Request $request, bool $shouldRedirect): void
    {
        /*
         * Usually the resolver could be instantiated in the setUp method, but
         * we need to be able to set the master-request's config here, since
         * the resolver reads the whitelist from it.
         */
        $resolver = new MaintenanceModeResolver($this->getRequestStack($request));

        if ($shouldRedirect) {
            static::assertTrue(
                $resolver->shouldRedirect($request),
                'Expected to be redirected to the maintenance page, but shouldRedirect returned false.'
            );
        } else {
            static::assertFalse(
                $resolver->shouldRedirect($request),
                'Didn\'t expect to be redirected to the maintenance page, but shouldRedirect returned true.'
            );
        }
    }

    /**
     * Tests if the resolver redirects requests from the maintenance page to the shop correctly.
     *
     * @dataProvider maintenanceModeInactiveProvider
     * @dataProvider maintenanceModeActiveProvider
     */
    public function testShouldRedirectToShop(Request $request, bool $shouldRedirect): void
    {
        $resolver = new MaintenanceModeResolver($this->getRequestStack($request));

        if ($shouldRedirect) {
            static::assertFalse(
                $resolver->shouldRedirectToShop($request),
                'Expected to be redirected from the maintenance page, but shouldRedirectToShop returned true.'
            );
        } else {
            static::assertTrue(
                $resolver->shouldRedirectToShop($request),
                'Didn\'t expect to not be redirected from the maintenance page, but shouldRedirectToShop returned false.'
            );
        }
    }

    /**
     * Test if the maintenance mode is active by request.
     *
     * @dataProvider maintenanceModeInactiveProvider
     * @dataProvider maintenanceModeActiveProvider
     */
    public function testIsMaintenanceRequest(Request $request, bool $expected): void
    {
        static::assertEquals(
            (new MaintenanceModeResolver($this->getRequestStack($request)))->isMaintenanceRequest($request),
            $expected
        );
    }

    /**
     * @return array<string, array{0: Request, 1: boolean}>
     */
    public function maintenanceModeInactiveProvider(): array
    {
        return [
            'maintenance mode is inactive, no sales channel request' => [
                $this->getRequest(false, false, false, false, false, false),
                false,
            ],
            'maintenance mode is inactive, sales channel requested' => [
                $this->getRequest(false, false, false, false, true, false),
                false,
            ],
            'maintenance mode is inactive, no sales channel request, proxy' => [
                $this->getRequest(true, false, false, false, false, false),
                false,
            ],
            'maintenance mode is inactive, sales channel requested, proxy' => [
                $this->getRequest(true, false, false, false, true, false),
                false,
            ],
        ];
    }

    /**
     * @return array<string, array{0: Request, 1: boolean}>
     */
    public function maintenanceModeActiveProvider(): array
    {
        return [
            'maintenance mode is active, sales channel requested' => [
                $this->getRequest(false, false, false, false, true, true),
                true,
            ],
            'maintenance mode is active, sales channel requested, client-ip' => [
                $this->getRequest(false, false, false, false, true, true),
                true,
            ],
            'maintenance mode is active, sales channel requested, whitelisted client ip' => [
                $this->getRequest(false, false, false, false, true, true, ['192.168.2.16', '192.168.1.16']),
                false,
            ],
            'maintenance mode is active, sales channel requested, whitelisted loopback ip' => [
                $this->getRequest(false, false, false, false, true, true, ['127.0.0.1', '::1']),
                true,
            ],
            'maintenance mode is active, sales channel requested, proxy' => [
                $this->getRequest(true, false, false, false, true, true),
                true,
            ],
            'maintenance mode is active, sales channel requested, proxy, client-ip' => [
                $this->getRequest(true, false, false, false, true, true),
                true,
            ],
            'maintenance mode is active, sales channel requested, proxy, whitelisted client ip' => [
                $this->getRequest(true, false, false, false, true, true, ['192.168.2.16', '192.168.1.16']),
                false,
            ],
            'maintenance mode is active, sales channel requested, proxy, whitelisted loopback ip' => [
                $this->getRequest(true, false, false, false, true, true, ['127.0.0.1', '::1']),
                true,
            ],
            'maintenance mode is active, sales channel requested, proxy, whitelisted client ip - mixed case' => [
                $this->getRequest(true, false, false, false, true, true, ['2003:F0:3f08:Db00:6D4:c4Ff:Fe48:74F4'], '2003:f0:3F08:dB00:6d4:C4fF:fE48:74f4'),
                false,
            ],
        ];
    }

    /**
     * @return array<string, array{0: Request, 1: boolean}>
     */
    public function xmlHttpRequestProvider(): array
    {
        return [
            'maintenance mode is active, sales channel requested, ajax' => [
                $this->getRequest(false, true, false, false, true, true),
                false,
            ],
            'maintenance mode is active, maintenance page requested, ajax' => [
                $this->getRequest(false, true, false, true, false, true),
                false,
            ],
            'maintenance mode is active, sales channel requested, ajax, proxy' => [
                $this->getRequest(true, true, false, false, true, true),
                false,
            ],
            'maintenance mode is active, maintenance page requested, ajax, proxy' => [
                $this->getRequest(true, true, false, true, false, true),
                false,
            ],
        ];
    }

    /**
     * @return array<string, array{0: Request, 1: boolean}>
     */
    public static function maintenancePageRequestProvider(): array
    {
        return [
            'maintenance mode is active, maintenance page requested' => [
                self::getRequest(false, false, false, true, false, true),
                false,
            ],
            'maintenance mode is active, maintenance page requested, proxy' => [
                self::getRequest(true, false, false, true, false, true),
                false,
            ],
        ];
    }

    /**
     * @return array<string, array{0: Request, 1: boolean}>
     */
    public static function errorControllerRequestProvider(): array
    {
        return [
            'maintenance mode is active, error controller requested' => [
                self::getRequest(false, false, true, false, false, true),
                false,
            ],
            'maintenance mode is active, error controller requested, proxy' => [
                self::getRequest(true, false, true, false, false, true),
                false,
            ],
        ];
    }

    private function getRequestStack(?Request $master = null): RequestStack
    {
        $requestStack = new RequestStack();

        if ($master !== null) {
            $requestStack->push($master);
        }

        return $requestStack;
    }

    /**
     * @param string[] $allowedIpAddresses
     */
    private static function getRequest(
        bool $useProxy,
        bool $isXmlHttpRequest,
        bool $isErrorControllerRequest,
        bool $isMaintenancePageRoute,
        bool $isSalesChannelRequest,
        bool $isMaintenanceModeActive,
        array $allowedIpAddresses = [],
        string $clientIp = '192.168.1.16'
    ): Request {
        $request = new Request();

        if ($isXmlHttpRequest) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        if ($isErrorControllerRequest) {
            $request->attributes->set('_route', null);
            $request->attributes->set('_controller', 'error_controller');
        }

        if ($isMaintenancePageRoute) {
            $request->attributes->set('_route', 'frontend.maintenance');
            $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE, true);
        }

        if ($useProxy) {
            $proxyIp = '172.17.1.12';
            $request->server->set('REMOTE_ADDR', $proxyIp);

            $request::setTrustedProxies([$proxyIp], Request::HEADER_FORWARDED);
            $request->headers->set('Forwarded', sprintf('by=%s;for=%s', $proxyIp, $clientIp));
        } else {
            $request->server->set('REMOTE_ADDR', $clientIp);
        }

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, $isSalesChannelRequest);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $isMaintenanceModeActive);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, json_encode($allowedIpAddresses, \JSON_THROW_ON_ERROR));

        return $request;
    }
}

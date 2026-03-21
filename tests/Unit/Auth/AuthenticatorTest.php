<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Auth;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Exceptions\AuthenticationException;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadConfig;
use PHPUnit\Framework\TestCase;

class AuthenticatorTest extends TestCase
{
    private SadadConfig $config;

    protected function setUp(): void
    {
        $this->config = new SadadConfig(
            merchantId:  '7015085',
            secretKey:   'T1ds45#sGQbodf5',
            website:     'www.example.com',
            environment: 'test',
            language:    'eng',
        );
    }

    private function makeHttpClient(array $response): HttpClientInterface
    {
        return new class($response) implements HttpClientInterface {
            public array $lastPostUrl    = [];
            public array $lastPostData   = [];
            public array $lastPostHeaders = [];

            public function __construct(private array $response) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                $this->lastPostUrl     = [$url];
                $this->lastPostData    = $data;
                $this->lastPostHeaders = $headers;

                if ($this->response === ['__throw__' => true]) {
                    throw new SadadException('HTTP failure', 'HTTP_ERROR');
                }

                return $this->response;
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                return [];
            }
        };
    }

    // -----------------------------------------------------------------------
    // login() - correct URL and payload
    // -----------------------------------------------------------------------

    public function testLoginCallsCorrectUrl(): void
    {
        $http = $this->makeHttpClient(['accessToken' => 'tok_abc']);
        $auth = new Authenticator($this->config, $http);

        $auth->login();

        $this->assertSame(
            'https://api-s.sadad.qa/api/userbusinesses/login',
            $http->lastPostUrl[0]
        );
    }

    public function testLoginSendsCorrectPayload(): void
    {
        $http = $this->makeHttpClient(['accessToken' => 'tok_abc']);
        $auth = new Authenticator($this->config, $http);

        $auth->login();

        $this->assertSame(7015085, $http->lastPostData['sadadId']);
        $this->assertSame('T1ds45#sGQbodf5', $http->lastPostData['secretKey']);
        $this->assertSame('www.example.com', $http->lastPostData['domain']);
    }

    public function testLoginSadadIdIsInteger(): void
    {
        $http = $this->makeHttpClient(['accessToken' => 'tok_abc']);
        $auth = new Authenticator($this->config, $http);

        $auth->login();

        $this->assertIsInt($http->lastPostData['sadadId']);
    }

    // -----------------------------------------------------------------------
    // login() - returns access token
    // -----------------------------------------------------------------------

    public function testLoginReturnsAccessToken(): void
    {
        $http = $this->makeHttpClient(['accessToken' => 'tok_xyz_123']);
        $auth = new Authenticator($this->config, $http);

        $token = $auth->login();

        $this->assertSame('tok_xyz_123', $token);
    }

    // -----------------------------------------------------------------------
    // getAccessToken() - caching
    // -----------------------------------------------------------------------

    public function testGetAccessTokenCachesToken(): void
    {
        $callCount = 0;
        $http = new class($callCount) implements HttpClientInterface {
            public int $postCallCount = 0;

            public function post(string $url, array $data = [], array $headers = []): array
            {
                $this->postCallCount++;
                return ['accessToken' => 'cached_token'];
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                return [];
            }
        };

        $auth = new Authenticator($this->config, $http);

        $token1 = $auth->getAccessToken();
        $token2 = $auth->getAccessToken();

        $this->assertSame('cached_token', $token1);
        $this->assertSame('cached_token', $token2);
        $this->assertSame(1, $http->postCallCount, 'HTTP should only be called once due to caching');
    }

    // -----------------------------------------------------------------------
    // login() - throws AuthenticationException when no accessToken
    // -----------------------------------------------------------------------

    public function testLoginThrowsWhenNoAccessToken(): void
    {
        $http = $this->makeHttpClient(['someOtherKey' => 'value']);
        $auth = new Authenticator($this->config, $http);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No access token in response');

        $auth->login();
    }

    public function testLoginThrowsWhenAccessTokenIsEmpty(): void
    {
        $http = $this->makeHttpClient(['accessToken' => '']);
        $auth = new Authenticator($this->config, $http);

        $this->expectException(AuthenticationException::class);

        $auth->login();
    }

    // -----------------------------------------------------------------------
    // login() - throws AuthenticationException when HTTP fails
    // -----------------------------------------------------------------------

    public function testLoginThrowsAuthenticationExceptionWhenHttpFails(): void
    {
        $http = $this->makeHttpClient(['__throw__' => true]);
        $auth = new Authenticator($this->config, $http);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed: HTTP failure');

        $auth->login();
    }

    public function testLoginWrapsOriginalExceptionAsPrevious(): void
    {
        $http = $this->makeHttpClient(['__throw__' => true]);
        $auth = new Authenticator($this->config, $http);

        try {
            $auth->login();
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertInstanceOf(SadadException::class, $e->getPrevious());
        }
    }
}

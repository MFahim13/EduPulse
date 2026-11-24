<?php
declare(strict_types=1);

/**
 * Talks to the real app through Apache (inside the app container) with its own cookies.
 */
abstract class HttpTestCase extends IntegrationTestCase
{
    /** @var array<string, string> */
    protected array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = [];
    }

    protected function baseUrl(): string
    {
        return rtrim(getenv('TEST_BASE_URL') ?: 'http://localhost', '/');
    }

    protected function get(string $path): HttpResponse
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, string|array{path: string, name?: string, type?: string}> $files
     */
    protected function post(string $path, array $fields, array $files = []): HttpResponse
    {
        return $this->request('POST', $path, $fields, $files);
    }

    /** GET the page, copy its CSRF token if there is one, then POST to the action. */
    protected function submitForm(string $pagePath, string $actionPath, array $fields, array $files = []): HttpResponse
    {
        $page = $this->get($pagePath);
        if (!array_key_exists('_csrf', $fields) && preg_match('/name="_csrf" value="([^"]+)"/', $page->body, $m)) {
            $fields['_csrf'] = $m[1];
        }
        return $this->post($actionPath, $fields, $files);
    }

    protected function loginAs(string $email, string $password): HttpResponse
    {
        $response = $this->submitForm('/login.php', '/actions/auth.php', [
            'action' => 'login',
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertSame(302, $response->status, "login failed for {$email}");
        return $response;
    }

    protected function follow(HttpResponse $response): HttpResponse
    {
        $location = $response->location();
        $this->assertNotNull($location, 'response has no Location header');
        $path = parse_url($location, PHP_URL_PATH) ?? '/';
        $query = parse_url($location, PHP_URL_QUERY);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $this->get($path . ($query ? '?' . $query : ''));
    }

    protected function request(string $method, string $path, array $fields = [], array $files = []): HttpResponse
    {
        $ch = curl_init($this->baseUrl() . $path);
        $headers = [];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ];
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $options[CURLOPT_COOKIE] = implode('; ', $pairs);
        }
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $files
                ? $this->multipart($fields, $files)
                : http_build_query($fields);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $this->fail('HTTP request failed: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        foreach ($headers['set-cookie'] ?? [] as $cookie) {
            $this->storeCookie($cookie);
        }

        return new HttpResponse($status, $headers, (string) $body);
    }

    private function multipart(array $fields, array $files): array
    {
        $data = [];
        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $v) {
                    $data["{$name}[{$i}]"] = (string) $v;
                }
            } else {
                $data[$name] = (string) $value;
            }
        }
        foreach ($files as $name => $file) {
            $file = is_string($file) ? ['path' => $file] : $file;
            $data[$name] = new CURLFile(
                $file['path'],
                $file['type'] ?? 'application/octet-stream',
                $file['name'] ?? basename($file['path'])
            );
        }
        return $data;
    }

    private function storeCookie(string $header): void
    {
        $parts = array_map('trim', explode(';', $header));
        [$name, $value] = array_pad(explode('=', array_shift($parts), 2), 2, '');
        $expired = $value === 'deleted';
        foreach ($parts as $part) {
            if (stripos($part, 'max-age=') === 0 && (int) substr($part, 8) <= 0) {
                $expired = true;
            }
            if (stripos($part, 'expires=') === 0 && strtotime(substr($part, 8)) < time()) {
                $expired = true;
            }
        }
        if ($expired) {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }
}

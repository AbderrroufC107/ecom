<?php
if (!function_exists('next_customer_frontend_url')) {
    function next_customer_frontend_url()
    {
        $url = trim((string) getenv('NEXT_CUSTOMER_FRONTEND_URL'));
        if ($url === '') {
            $host = '127.0.0.1';
            if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') {
                $host = preg_replace('/:.*$/', '', $_SERVER['HTTP_HOST']);
            }
            $url = 'http://' . $host . ':3000';
        }

        return rtrim($url, '/');
    }
}

if (!function_exists('next_customer_is_available')) {
    function next_customer_is_available($baseUrl)
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        $parts = parse_url($baseUrl);
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return $available = false;
        }

        // Force localhost to 127.0.0.1 to avoid Windows IPv6 (::1) socket resolution failures
        $checkHost = ($host === 'localhost') ? '127.0.0.1' : $host;

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $socketHost = ($scheme === 'https' ? 'ssl://' : '') . $checkHost;
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, 0.25);
        if (is_resource($socket)) {
            fclose($socket);
            return $available = true;
        }

        return $available = false;
    }
}

if (!function_exists('next_customer_redirect')) {
    function next_customer_redirect($route, array $query = [])
    {
        if (PHP_SAPI === 'cli' || isset($_GET['legacy'])) {
            return;
        }

        // Prevent browser caching of redirection state
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        $baseUrl = next_customer_frontend_url();
        if (!next_customer_is_available($baseUrl)) {
            return;
        }

        $route = '/' . ltrim((string) $route, '/');
        $query = array_filter($query, static function ($value) {
            return $value !== null && $value !== '';
        });
        $url = $baseUrl . $route;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        header('Location: ' . $url, true, 302);
        exit;
    }
}


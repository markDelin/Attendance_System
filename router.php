<?php
/**
 * router.php
 * Fixes MIME type issues for PHP Built-in Server in Termux/Android.
 */

$uri = decodeURI($_SERVER['REQUEST_URI']);
$file = __DIR__ . $uri;

if (is_file($file)) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $mimetypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'application/font-woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'application/font-sfnt',
        'otf'  => 'application/font-sfnt',
        'mp3'  => 'audio/mpeg',
    ];

    if (isset($mimetypes[$extension])) {
        header("Content-Type: " . $mimetypes[$extension]);
    }
    
    readfile($file);
    return true;
}

// Fallback to index if file not found (standard PHP-S behavior if we return false)
return false;

function decodeURI($uri) {
    return rawurldecode(explode('?', $uri)[0]);
}

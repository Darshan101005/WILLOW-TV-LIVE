<?php
// Get URL from path (after /api/)
$requestUri = $_SERVER['REQUEST_URI'];
$url = substr($requestUri, strpos($requestUri, '/api/') + 5);

if (empty($url)) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid URL - Usage: /api/[stream_url]');
}

// Fix common URL encoding issues
$url = urldecode($url);

// Fix double slashes that might occur after /api/https:/ (becomes https://)
$url = preg_replace('/(https?):\/([^\/])/', '$1://$2', $url);

// Validate URL format
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    // Try adding https:// if missing
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('HTTP/1.1 400 Bad Request');
        die('Invalid URL format: ' . htmlspecialchars($url));
    }
}

// Configure request
$options = [
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9'
        ]),
        'follow_location' => true,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('Failed to fetch stream from: ' . htmlspecialchars($url));
}

// Forward content type
foreach ($http_response_header as $header) {
    if (preg_match('/^content-type:/i', $header)) {
        header($header);
        break;
    }
}

// Process HLS playlists
if (isset($header) && (strpos($header, 'application/vnd.apple.mpegurl') !== false || 
    strpos($header, 'application/x-mpegURL') !== false)) {
    
    $proxyBase = 'https://'.$_SERVER['HTTP_HOST'].'/api/';
    $lines = explode("\n", $response);
    
    foreach ($lines as &$line) {
        if (strpos($line, 'http') === 0) {
            $line = $proxyBase . $line;
        }
        elseif (!empty($line) && $line[0] !== '#' && strpos($line, '://') === false) {
            $absoluteUrl = dirname($url) . '/' . $line;
            $line = $proxyBase . $absoluteUrl;
        }
    }
    
    $response = implode("\n", $lines);
}

echo $response;
?>

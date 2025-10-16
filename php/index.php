<?php
use Jazor\Http\Request;
use Jazor\Uri;

include_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/helper.php';

const HUB_HOST = 'registry-1.docker.io';
const AUTH_HOST = 'auth.docker.io';
const AUTH_BASE = 'https://auth.docker.io';
const TUNNEL_PROXY_START = '/TUNNEL_PROXY_START/';

set_time_limit(0);
ob_implicit_flush(true);

$headers = get_request_headers();
unset($headers['Host']);
unset($headers['Accept-Encoding']); // 禁止 gzip 压缩防止转发异常

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$host = $_SERVER['HTTP_HOST'];
$scheme = $_SERVER['REQUEST_SCHEME'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http');

if ($requestUri === '/testing') {
    header('content-type: text/plain');
    echo "docker proxy ok!\n";
    exit();
}

# local base, 用于重写 token 地址
$localBase = $scheme . '://' . $host;

# 决定代理的上游主机
$newHost = (strpos($requestUri, '/token?') === 0) ? AUTH_HOST : HUB_HOST;

# 如果是内部隧道代理
if (strpos($requestUri, TUNNEL_PROXY_START) === 0) {
    $url = urldecode(substr($requestUri, strlen(TUNNEL_PROXY_START)));

    # 安全检查，只允许 docker.com 相关域名
    $hostName = (new Uri($url))->getAuthority();
    if (strpos($hostName, '.docker.com') !== strlen($hostName) - 11) {
        header('HTTP/1.1 403 Forbidden');
        echo "Forbidden external domain\n";
        exit();
    }
    $newUri = $url;
} else {
    $newUri = 'https://' . $newHost . $requestUri;
}

# 构造请求
$req = new Request($newUri, $method);
foreach ($headers as $name => $value) {
    $req->setHeader($name, $value);
}
$req->setHeader('Connection', 'close');

# 获取响应
$response = $req->getResponse([
    'sslVerifyPeer' => false,
    'sslVerifyHost' => false,
]);

# 输出状态码
header('HTTP/1.1 ' . $response->getStatusCode() . ' ' . $response->getStatusText());

# 转发常用头部
$contentType = $response->getContentType();
if ($contentType) send_header('Content-Type', $contentType);

$contentLength = $response->getContentLength();
if ($contentLength >= 0) send_header('Content-Length', $contentLength);

# 修改认证地址
$auth = $response->getSingletHeader('Www-Authenticate');
if ($auth) {
    $auth = str_replace(AUTH_BASE, $localBase, $auth);
    send_header('Www-Authenticate', $auth);
}

# 处理 Location 重定向逻辑
$location = $response->getLocation();
if ($location) {
    $uri = new Uri($location);

    // ✅ 对 Docker Hub / Token 地址重写为本地
    if (strpos($location, AUTH_BASE) === 0 || strpos($location, HUB_HOST) !== false) {
        $location = str_replace(AUTH_BASE, $localBase, $location);
        $location = str_replace('https://' . HUB_HOST, $localBase, $location);
        send_header('Location', $location);
    } else {
        // ✅ 对 CDN 地址（如 Cloudflare、AWS）保持原样，客户端直连
        send_header('Location', $location);
    }
}

# HEAD 请求不带 body
if ($contentLength === 0 || $method === 'HEAD') exit();

# 对于有明确长度的响应
if ($contentLength > 0) {
    $response->sink('php://output');
    exit();
}

# 否则直接输出响应体
echo $response->getBody();

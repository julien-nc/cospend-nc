<?php
/**
 * PHP built-in web server router for Nextcloud integration tests.
 *
 * Routes virtual paths like /.well-known/ocm and /ocm-provider through
 * index.php, while letting the built-in server handle real files and
 * PHP scripts (including PATH_INFO paths like /ocs/v2.php/apps/...).
 *
 * Usage:
 *   php -S localhost:8080 -t /path/to/nextcloud /path/to/router.php
 */

declare(strict_types=1);

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$docRoot = $_SERVER['DOCUMENT_ROOT'];

// Serve existing files directly
if (is_file($docRoot . $path)) {
	return false;
}

// Walk up the path to find a .php script — handles PATH_INFO paths
// e.g. /ocs/v2.php/apps/cospend/api/v1/projects -> found ocs/v2.php
$parts = explode('/', ltrim($path, '/'));
$candidate = '';
foreach ($parts as $part) {
	$candidate .= '/' . $part;
	if (is_file($docRoot . $candidate) && str_ends_with($candidate, '.php')) {
		return false; // PHP built-in server handles it with PATH_INFO
	}
}

// Virtual paths (/.well-known/ocm, /ocm-provider, etc.) -> index.php
// Set SCRIPT_NAME so Nextcloud's getRawPathInfo() can compute the path correctly
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docRoot . '/index.php';
include $docRoot . '/index.php';

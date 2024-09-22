<?php

// DEBUGGING
$debugging = 1;

if($debugging === 1) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(0);
  ini_set('display_errors', 0);
}
// Database
ini_set('max_allowed_packet', '16M');
ini_set('sort_buffer_size', '3M');
ini_set('max_connections', '50');
ini_set('memory_limit', '256M');

// Session
ini_set('session.gc_maxlifetime', 3600); // 3600 seconds = 1 hour
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

ini_set('session.hash_function', 1);
ini_set('session.hash_bits_per_character', 4);
ini_set('session.hash_algorithm', 'sha512');

// Network
ini_set('default_socket_timeout', 30);
ini_set('zlib.output_compression', 'On');

// Security
header("X-Frame-Options: deny");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Permitted-Cross-Domain-Policies: none");
header("Referrer-Policy: no-referrer");
header("Cross-Origin-Embedder-Policy: require-corp");
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");
header("Permissions-Policy: accelerometer=(), autoplay=(), camera=(), cross-origin-isolated=(), display-capture=(), encrypted-media=(), fullscreen=(), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(self), usb=(), web-share=(), xr-spatial-tracking=(), gamepad=(), hid=(), idle-detection=(), interest-cohort=(), serial=(), unload=()");
header_remove("X-Powered-By");
header_remove("Server");

// SSL
if (is_ssl()) {
  ini_set('session.cookie_secure', 1);
  header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
} else {
  ini_set('session.cookie_secure', 0);
}

$isSSL = (is_ssl()) ? true : false;

$serverIP = ($debugging === 1) ? 'localhost' : $_SERVER['HTTP_HOST'];

// Set session cookie
session_set_cookie_params([
  'lifetime' => 3600,
  'path' => '/',
  'domain' => $serverIP,
  'secure' => $isSSL,
  'httponly' => true,
  'samesite' => 'Strict',
]);

session_start();

// Server
$servername = "📢Server";

$db_messages_name = 'db/chat.db';
$db_messages_colums = 'chatCode TEXT, messageId TEXT, sender TEXT, message TEXT, isread BOOLEAN, sentAt DATETIME DEFAULT CURRENT_TIMESTAMP';
$db_messages = createDatabase($db_messages_name, 'chatMessages', $db_messages_colums, 'file');

$db_users_name = 'db/users.db';
$db_users_colums = 'userId TEXT, chatCode TEXT, isRead BOOLEAN DEFAULT 0, userStatus TEXT DEFAULT "offline"';
$db_users = createDatabase($db_users_name, 'users', $db_users_colums, 'file');

if($db_users) {
  $id = uniqid();
}

function createDatabase(string $dbName, string $tableName, string $columns, string $fileOrMemory = 'file') {
  try {
    if ($fileOrMemory === 'memory') {
      $db = new SQLite3('db/file::memory:?mode=memory&cache=shared');
    } else {
      if (!file_exists($dbName)) {
        if (touch($dbName)) {
          chmod($dbName, 0600);
        } else {
          http_response_code(500); // Internal Server Error
          die("Can't create Database file.");
        }
      }
      $db = new SQLite3($dbName);
    }
  
    if (!isset($db) || !$db) {
      http_response_code(500); // Internal Server Error
      echo $db->lastErrorMsg();
      die('Failed to open database');
    }
  
    $db->exec("PRAGMA foreign_keys = ON;");
  
    $db->enableExceptions(true);
    $db->busyTimeout(30000);
  
    $db->exec("PRAGMA journal_mode = WAL");
    $db->exec("PRAGMA synchronous = FULL");
    $db->exec("PRAGMA temp_store = MEMORY");
    $db->exec("PRAGMA cache_spill = OFF");
    $db->exec("PRAGMA encoding = 'UTF-8'");
    $db->exec("PRAGMA auto_vacuum = incremental");
    $db->exec("PRAGMA quick_check");
    $db->exec("PRAGMA cell_size_check=ON");
  
    $attempts = 0;
    while ($attempts < 5) {
      try {
        $db->exec("CREATE TABLE IF NOT EXISTS $tableName ($columns)");
        break;
      } catch (Exception $e) {
        if ($e->getMessage() === 'database is locked') {
          $attempts++;
          sleep(1);
        } else {
          http_response_code(500); // Internal Server Error
        }
      }
    }
    return $db;
  } catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    die("Failed to open database ".$e->getMessage());
  }
}

function VarVeri($var) {
  if(isset($var) && !empty($var)) {
    $var = (string) trim($var);
    $var = strip_tags($var, '<b><i><u><mark>');
    $var = sanitizeTags($var);
    $out = mb_convert_encoding($var, 'UTF-8');
    return ($out);
  } else {
    return '';
  }
}

function sanitizeTags($input) {
  return preg_replace('/<\s*(b|i|u|mark)\b[^>]*>/', '<$1>', $input);
}

// error handler function
function log_to_console($data) {
  global $debugging;
  if($debugging === 1) {
    $erroutput = json_encode($data);
    echo mb_convert_encoding($erroutput, 'UTF-8');
  }
}

// Filter chatroom names to specs
function filterInput($input) {
  $filteredInput = preg_replace("/[^a-zA-Z0-9]+/", "", $input); // Remove any characters that are not letters or numbers
  return $filteredInput;
}

// Generate CSRF token
function generateCsrfToken() {
  if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_generated_at']) || $_SESSION['csrf_token_generated_at'] < time() - 3600) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
    $_SESSION['csrf_token_generated_at'] = time();
  } else {
    $csrfToken = $_SESSION['csrf_token'];
  }
  return $csrfToken;
}

// Validate CSRF token
function validateCsrfToken() {
  if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_generated_at']) || $_SESSION['csrf_token_generated_at'] < time() - 3600) {
    return false;
  }
  return true;
}

// Validate CSRF Token
function validateCsrfTokenHash($token) {
  if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token']) || empty($token)) {
    return false;
  } else if(hash_equals($_SESSION['csrf_token'], $token)) {
    return true;
  }
  return false;
}

// Validate Nonce
function validateNonceHash($nonce) {
  if (!isset($_SESSION['nonce']) || empty($_SESSION['nonce']) || empty($nonce)) {
    return false;
  }
  return hash_equals($_SESSION['nonce'], $nonce);
}

// Generate Nonce
function generateNonce() {
  if (!isset($_SESSION['nonce'])) {
    clearHtmlCache();
    $_SESSION['nonce'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['nonce'];
}

// Clear Cache
function clearHtmlCache() {
  if (isset($_SERVER['HTTP_CACHE_CONTROL'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
  }
}

// Add server to db
function addServerToUsersTable($serverID) {
  if(empty($serverID)) {
    return false;
  }

  // Only add if not exsist and on first run

  global $db_users;
  global $servername;
  $query = "INSERT INTO users (userId, chatCode, userStatus) VALUES (?, ?, ?)";
  $stmt = $db_users->prepare($query);
  $stmt->bindValue(1, $servername);
  $stmt->bindValue(2, $serverID);
  $stmt->bindValue(3, 'online');
  $stmt->execute();

  if($stmt) {
    return true;
  }
}

// Check SSL
function is_ssl() {
  if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    return true;
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    return true;
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    return true;
  }
  return false;
}

// Enforce HTTPS
/*if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
  header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}*/

// Verify the request is coming from the same origin
/*if (!isset($_SERVER['HTTP_ORIGIN']) || $_SERVER['HTTP_ORIGIN']!== $serverIP) {
  http_response_code(403);
  exit;
}*/

session_write_close();
?>
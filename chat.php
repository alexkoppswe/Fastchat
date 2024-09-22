<?php
include_once("config.php");
session_start();

global $db_messages;
global $db_users;
  
set_error_handler('errorHandler');

ob_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');
header('Connection: keep-alive');

function errorHandler($errno, $errstr, $errfile, $errline) {
  http_response_code(500); // Internal Server Error
  log_to_console("Error: $errstr in $errfile on line $errline");
  echo '<div>Error occurred. Redirecting...</div>';
  //header("Location: index.php");
  exit;
}

function checkUserID() {
  if (!isset($_SESSION['UserID'])) {
    sleep(1);
    if (!isset($_SESSION['UserID']) || empty($_SESSION['UserID'])) {
      http_response_code(401); // Unauthorized
      errorEnd();
      endChat();
      exit;
    }
  }
}

function checkChatCode() {
  if (!isset($_SESSION['ChatCode']) || empty($_SESSION['ChatCode'])) {
    log_to_console("ChatID");
    http_response_code(400); // Bad Request
    errorEnd();
    exit;
  }
}

// Function to execute a database query
function dbQuery($db, $query, $params = array()) {

  if(!$db) { return false; }

  $db->exec('BEGIN TRANSACTION');
  try {
    $stmt = $db->prepare($query);
    if (!$stmt) {
      throw new Exception("Error preparing statement: ". $db->lastErrorMsg());
    }
    if (!empty($params)) {
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
    }
    $result = $stmt->execute();
    $db->exec('COMMIT');
    if($result) {
      http_response_code(200);
      return $result;
    } else {
      return false;
    }

  } catch (Exception $e) {
    $db->exec('ROLLBACK');
    log_to_console($e->getMessage());
    http_response_code(500); // Internal Server Error
    return false;
  }
}

// What happens after a error
function errorEnd() {
  endChat();
  exit;
}

function softReload() {
  clearstatcache();
  clearHtmlCache();
  echo '<script>location.reload();</script>';
  exit;
}

// Logout - Exit
function endChat() {
  global $db_messages;
  global $db_users;

  clearstatcache();
  clearHtmlCache();

  if(isset($_SESSION['UserID'])) {
    $sender = $_SESSION['UserID'];
    $shortSender = trimUser($sender);
    $message = $shortSender." Left the chat!";
    insertServerMessage($message);
    manageUserStatus('delete', $sender);
  }

  $db_users->close();
  $db_messages->close();
  ob_end_clean();
  session_destroy();
  http_response_code(302);  // Moved Temporarily
  die(header("Location: index.php"));
}

// Check if the user first joined
if (!isset($_SESSION['userJoined'])) {
  checkUserID();
  $_SESSION['userJoined'] = true;
  $sender = $_SESSION['UserID'];
  $shortSender = trimUser($sender);
  $message = $shortSender." Joined the chat!";
  insertServerMessage($message);
  manageUserStatus('insert', $sender);
}

// Utility function to check for empty input and IV length
function validateOpenSSL($input, $iv) {
  if (empty($input)) {
    return log_to_console('input is empty');
  }
  if (strlen($iv)!== 16) {
    return;
  }
  return true;
}

// Encryption function
function encryptMessage($message, $key) {
  $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
  if (!validateOpenSSL($message, $iv)) {
    //log_to_console('encrypt');
    return false;
  }
  $encryptedMessage = openssl_encrypt($message, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  $encryptedData = $iv. $encryptedMessage;
  $encodedData = base64_encode($encryptedData);
  return $encodedData;
}

// Decryption function
function decryptMessage($message, $key) {
  $decodedData = base64_decode($message);
  $iv = substr($decodedData, 0, openssl_cipher_iv_length('AES-256-CBC'));
  if (!validateOpenSSL($message, $iv)) {
    //log_to_console('decrypt'.$iv);
    return false;
  }
  $encryptedMessage = substr($decodedData, openssl_cipher_iv_length('AES-256-CBC'));
  $decryptedMessage = openssl_decrypt($encryptedMessage, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  return $decryptedMessage;
}

// Function to insert a system message into the database
function insertServerMessage($inMessage) {
  checkChatCode();
  global $db_messages;
  global $servername;
  $sender = $servername;
  $message = trim($inMessage);
  $messageId = uniqid(); // Improve
  $encryptedMessage = encryptMessage($message, $_SESSION['ChatCode']);

  $query = "INSERT INTO chatMessages (chatCode, messageId, sender, message, isread, sentAt) VALUES (:chatCode, :messageId, :sender, :message, 0, :sentAt)";
  $params = array(
    ':chatCode' => $_SESSION['ChatCode'],
    ':messageId' => $messageId,
    ':sender' => $sender,
    ':message' => $encryptedMessage,
    ':sentAt' => date('Y-m-d H:i:s')
  );
  
  $result = dbQuery($db_messages, $query, $params);

  if(!empty($result)) {
    return true;
  } else {
    return false;
  }
}

// Function to output messages from the database
function retrieveUnreadChatMessages() {
  checkChatCode();
  global $db_messages;

  $query = "SELECT * FROM chatMessages WHERE chatCode = :chatCode AND isread = 0 LIMIT 50";
  $params = array(':chatCode' => $_SESSION['ChatCode']);
  $result = dbQuery($db_messages, $query, $params);

  if ($result) {
    $messages = [];
    while ($message = $result->fetchArray(SQLITE3_ASSOC)) {
      $messages[] = $message;
    }

    $lastModified = gmdate('D, d M Y H:i:s', time()).'GMT';
    $html = outputChatMessages($messages);
    header("Last-Modified: $lastModified");
    return $html;
  }
}

// Function to output chat messages
function outputChatMessages($messages) {
  checkChatCode();
  checkUserID();
  global $servername;

  if (empty($messages)) {
    http_response_code(204); // No Content
    return '<p class="unresolved-message">No messages.</p>';
  }

  $html = '';
  $chatCode = $_SESSION['ChatCode'];
  $userID = $_SESSION['UserID'];

  foreach ($messages as $message) {
    $sender = ($message['sender'] === $servername) ? $servername : VarVeri($message['sender']);
    $secure = decryptMessage($message['message'], $chatCode);
    $messageText = $secure;
    $sentAt = date('H:i', strtotime($message['sentAt']));
    $allSentAt = date('[Y-m-d] H:i:s', strtotime($message['sentAt']));
    $secureOut = ($secure && is_ssl()) ? '🛡️' : '';
    $shortSender = ($sender === $userID) ? "<i>(You) </i>".trimUser($sender, 5) : trimUser($sender);

    $html.= sprintf(
      '<div id="chat-msg-inline">'
    . '<span class="chat-timestamp" title="%s">%s</span>'
    . '<span class="chat-security" title="Connection is using SSL and is encrypted on the server.">%s</span>'
    . '<div class="chat-color" style="color: %s;">'
    . '<span class="chat-sender" title="%s">%s: </span>'
    . '<span class="chat-message">%s</span>'
    . '</div></div>',
      $allSentAt,
      $sentAt,
      VarVeri($secureOut),
      getRandomColor($sender),
      $sender,
      $shortSender,
      VarVeri($messageText)
    );
  }
  http_response_code(200); // Success
  header("Content-Type: text/html; charset=utf-8");
  return $html;
}

function getUsersFromDatabase() {
  checkChatCode();
  global $db_users;
  $query = "SELECT userId, userStatus FROM users WHERE isRead = 0 AND chatCode = :chatCode";
  $params = array(
    ':chatCode' => $_SESSION['ChatCode']
  );
  $result = dbQuery($db_users, $query, $params);
  if ($result) {
    $users = [];
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
      $users[] = $user;
    }
  }

  $lastModified = gmdate('D, d M Y H:i:s', time()).'GMT';
  $html = outputUserHtml($users);
  header("Last-Modified: $lastModified");
  return $html;
}

// Manage user status
function manageUserStatus($action, $sender = null, $message = null) {
  checkChatCode();
  global $db_users;

  switch ($action) {
    case 'delete':
      if ($sender === null) {
        log_to_console("Error: Missing sender ID");
        http_response_code(400); // Bad Request
        return;
      }
      $query = "DELETE FROM users WHERE userId = :userId AND chatCode = :chatCode";
      $params = array(
        ':userId' => $sender,
        ':chatCode' => $_SESSION['ChatCode']
      );
      break;
    case 'insert':
      if ($sender === null) {
        log_to_console("Error: Missing sender ID");
        http_response_code(400); // Bad Request
        return;
      }
      $query = "INSERT INTO users (userId, chatCode, isRead, userStatus) VALUES (:userId, :chatCode, 0, 'online')";
      $params = array(
        ':userId' => $sender,
        ':chatCode' => $_SESSION['ChatCode']
      );
      break;
    case 'update':
      checkUserID();
      $messageIn = (isset($_GET['userStatus']) && !empty($_GET['userStatus'])) ? VarVeri($_GET['userStatus']) : $message;
      if ($messageIn !== null) {
        $status = VarVeri($messageIn); // whitelist status
        $query = "UPDATE users SET userStatus = :status WHERE userId = :userId AND chatCode = :chatCode";
        $params = array(
          ':userId' => $_SESSION['UserID'],
          ':chatCode' => $_SESSION['ChatCode'],
          ':status' => $status
        );
      } else {
        $query = "UPDATE users SET userStatus = 'online' WHERE userId = :userId AND chatCode = :chatCode";
        $params = array(
          ':userId' => $_SESSION['UserID'],
          ':chatCode' => $_SESSION['ChatCode']
        );
        echo getUsersFromDatabase();
      }
      break;
    default:
      log_to_console("Error: Invalid action");
      http_response_code(400); // Bad Request
      return;
  }

  try {
    dbQuery($db_users, $query, $params);
  } catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    log_to_console("Error: ". $e->getMessage());
  }
}

// Function to output users
function outputUserHtml($users) {
  $html = '<div id="user-list-ul">';
  if(isset($users) && $users !== []) {
    foreach ($users as $user) {
      $shortSender = trimUser($user['userId']);
      $status = !empty($user['userStatus']) ? htmlspecialchars($user['userStatus']) : '';
      if ($status === 'online') {
        $status = '<span class="online-status">Online</span>';
      } else if ($status === 'away') {
        $status = '<span class="away-status">Away</span>';
      } else if ($status === 'afk') {
        $status = '<span class="afk-status">Afk</span>';
      }
      $html.= '<div class="user-item" title="'.$user['userId'].'">
                <span class="user-item-name">'.htmlspecialchars($shortSender).'</span>
                <span class="user-item-status">'.$status.'</span>
                </div>';
    }
  } else {
    http_response_code(204); // No Content
    $html.= '<p class="unresolved-message">No users</p>';
  }
  $html.= '</div>';
  http_response_code(200); // Success
  header("Content-Type: text/html; charset=utf-8");
  return $html;
}

// Trim username lenght
function trimUser($username , $length = 10) {
  return substr($username, 0, $length). '';
}

// Semi-Random text color
function getRandomColor($userId) {
  global $servername;
  if ($userId === $servername) {
    return '#b12e2e';
  } else {
    $colors = array(
      '#ca8f37',
      '#4cb44c',
      '#4a4ab6',
      '#b9b94b',
      '#ad5dad',
      '#41a8a8',
      '#83b641',
      '#186918',
      '#2b79be',
      '#82308d',
      '#259870',
      '#5050af',
      '#9f2d71',
      '#874a98',
      '#1a8282',
    );
    $index = ord($userId) % count($colors);
    return $colors[$index];
  }
}

// Insert a new message into the database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_POST['csrf_token'])) {
  checkUserID();
  checkChatCode();
  
  // CSFR protection
  if (!validateCSRF('POST')) exit;

  $cleanMessage = VarVeri($_POST['message']);

  if (empty($cleanMessage)) {
    http_response_code(204); // No Content
    exit;
  }

  $messageId = uniqid();
  $encryptedMessage = encryptMessage($cleanMessage, $_SESSION['ChatCode']);

  // Clear the message input
  echo "<script>if (document.getElementById('message-input')) {document.getElementById('message-input').value = '';}</script>";
  echo "<script>if (document.getElementById('message-input')) {document.getElementById('message-input').style.height = 'initial';}</script>";

  $query = "INSERT INTO chatMessages (chatCode, messageId, sender, message, isread, sentAt) VALUES (:chatCode, :messageId, :sender, :message, 0, :sentAt)";
  $params = array(
    ':chatCode' => $_SESSION['ChatCode'],
    ':messageId' => $messageId,
    ':sender' => $_SESSION['UserID'],
    ':message' => $encryptedMessage,
    ':sentAt' => date('Y-m-d H:i:s') //datetime('now')
  );
  dbQuery($db_messages, $query, $params);

  // Update the chat after sent message
  echo retrieveUnreadChatMessages();
}

function validateCSRF($type = 'GET') {
  $token = '';

  if ($type === 'GET' && $_SERVER['REQUEST_METHOD'] === 'GET') { // || $type === 'HEAD'
    $headers = getallheaders();
    if (isset($headers['X-CSRF-Token'])) {
      $token = strip_tags($headers['X-CSRF-Token'] ?? '');
    }
  } else if ($type === 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
      $token = strip_tags($_POST['csrf_token'] ?? '');
    }
  }

  if (empty($token)) {
    log_to_console('Missing CSRF token');
    http_response_code(400); // Bad Request
    errorEnd();
    exit;
  }
  
  if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    log_to_console('Invalid CSRF token');
    http_response_code(400); // Bad Request
    errorEnd();
    exit;
  }

  if (!validateCsrfToken($token)) {
    log_to_console('Invalid CSRF token');
    http_response_code(401); // Unauthorized
    exit();
  }

  if (!validateCsrfTokenHash($token)) {
    log_to_console('CSRF token mismatch.');
    http_response_code(401); // Unauthorized
    errorEnd();
    exit;
  }

  return true;
}

function validateNonce($type = 'GET') {
  $nonce = '';

  if ($type === 'GET' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $headers = getallheaders();
    if (isset($headers['X-Nonce'])) {
      $nonce = strip_tags($headers['X-Nonce'] ?? '');
    }
  } else if ($type === 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce'])) {
      $nonce = VarVeri($_POST['nonce'] ?? '');
    }
  }

  if (empty($nonce)) {
    log_to_console('Missing nonce');
    http_response_code(400); // Bad Request
    errorEnd();
    exit;
  }

  if (!validateNonceHash($nonce)) {
    log_to_console('Nonce mismatch.');
    http_response_code(401); // Unauthorized
    errorEnd();
    exit;
  }

  return true;
}

// Handle GET requests
if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
  $action = VarVeri($_GET['action']);
  switch ($action) {
    case 'getUsers':
      if (!validateCSRF('GET')) exit;
      if (!validateNonce('GET')) exit;
      echo getUsersFromDatabase();
      break;
    case 'getMessages':
      if (!validateCSRF('GET')) exit;
      if (!validateNonce('GET')) exit;
      echo retrieveUnreadChatMessages();
      break;
    case 'end':
      if (!validateCSRF('GET')) exit;
      endChat();
      break;
    case 'updateStatus':
      if (!validateCSRF('GET')) exit;
      manageUserStatus('update');
      break;
    case 'heartbeat':
      //<div hx-get="chat.php?action=heartbeat" hx-trigger="every 1m" hx-debug></div>
      http_response_code(200); // Success
      break;
    default:
      http_response_code(404); // Not Found
      exit;
  }
}

ob_end_flush();
$db_messages->close();
?>
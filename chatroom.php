<?php
  include_once("config.php");
  set_time_limit(300); // 300 seconds (5 minutes)
  header('Keep-Alive: timeout=30, max=300');

  session_start();
  session_regenerate_id(true);

  if (!isset($_SESSION['UserID']) || !isset($_SESSION['ChatCode']) || empty($_SESSION['UserID']) || empty($_SESSION['ChatCode'])) {
    clearHtmlCache();
    http_response_code(401); // Unauthorized
    header("Location: index.php");
    exit();
  }

  // Generate CSRF Token
  if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_generated_at']) || $_SESSION['csrf_token_generated_at'] < time() - 3600) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
    $_SESSION['csrf_token_generated_at'] = time();
    clearHtmlCache();
  } else if (isset($_SESSION['csrf_token'])) {
    $csrfToken = $_SESSION['csrf_token'];
  } else {
    log_to_console('Invalid CSRF token');
    http_response_code(400); // Bad Request
    header("Location: index.php");
    exit;
  }

  // Get ChatCode from POST
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chatCode']) && preg_match('/^[A-Za-z0-9]{13}$/', $_POST['chatCode'])) {
    // Validate CSRF
    if (!isset($_SESSION['csrf_token_start']) || $_SESSION['csrf_token_start'] != $_POST['csrf_token_start']) {
      clearHtmlCache();
      log_to_console('Invalid CSRF token');
      http_response_code(400); // Bad Request
      header("Location: index.php");
      exit;
    }

    $chatCode = VarVeri($_POST['chatCode']);
  } else {
    $chatCode = $_SESSION['ChatCode'];
  }
  $_SESSION['ChatCode'] = $chatCode;

  // Dynamic html text
  if (is_ssl()) {
    $secure = '<span class="sender-hover" data-title="Secure connection">🔒</span>';
  } else {
    $secure = '<span class="sender-hover" data-title="Insecure connection">🔓</span>';
  }

  // CSP
  $nonce = generateNonce();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Secure Chat</title>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="private, max-age=657000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <link rel="stylesheet" type="text/css" as="style" media="all" href="style.css" nonce="<?php echo $nonce;?>" crossorigin="use-credentials">
    <!-- https://unpkg.com/htmx.org@2.0.1 -->
    <script src="js/htmx.min.2.0.1.js"> type="text/javascript" media="all" crossorigin="use-credentials" nonce="<?php echo $nonce;?>" defer></script>
    <script src="js/chat.js" type="text/javascript" media="all" nonce="<?php echo $nonce;?>" crossorigin="use-credentials" defer></script>
  </head>
  <body>
  <div id="container">
    <div id="chatbox">
      <div id="chat-titlebar">
        <div id="chat-titlebar-inner">
          <h3>FastChat</h3>
          <div class="corner-right">
            <?php echo $secure; ?>
            <a href="chat.php?action=end" id="xbutton" class="button3 sender-hover" data-title="Leave chat" hx-headers='{"X-CSRF-Token": "<?php echo $csrfToken;?>", "X-Nonce": "<?php echo $nonce;?>"}'>X</a>
          </div>
        </div>
        <div id="chat-titlebar-room">
          <span class="titlebar-room"><i>Chatroom ID: </i><span class="id-text"><?php echo $chatCode; ?></span>
            <span id="copyIcon"  class="sender-hover copy-icon" data-title="Copy to Clipboard">📋</span>
          </span>
          <span class="titlebar-base"><i>Your Username: </i><span class="id-text"><?php echo $_SESSION['UserID']; ?></span></span>
        </div>
      </div>
      <div id="chat-container">
        <div id="user-list" hx-get="chat.php?action=getUsers" hx-trigger="load, every 10s" hx-swap="outerHTML" hx-target="#user-list-ul" hx-poll="true" hx-headers='{"X-CSRF-Token": "<?php echo $csrfToken;?>", "X-Nonce": "<?php echo $nonce;?>"}' hx-debug>
          <h4>Users Online</h4>
          <div id="user-list-ul"></div>
        </div>
        <div id="chat-window" hx-get="chat.php?action=getMessages" hx-trigger="load, every 4s" hx-swap="innerHTML" hx-target="#chat-window" hx-poll="true" hx-headers='{"X-CSRF-Token": "<?php echo $csrfToken;?>", "X-Nonce": "<?php echo $nonce;?>"}' hx-debug></div>
      </div>
      <form id="message-form" hx-post="chat.php" hx-trigger="submit" hx-target="#chat-window" hx-headers='{"X-CSRF-Token": "<?php echo $csrfToken; ?>", "X-Nonce": "<?php echo $nonce;?>"}'>
        <span id="moji-bar" class="sender-hover" data-title="Emoji's">&#127946;</span>
        <div id="emoji-picker" class="hidden">
          <div id="emoji-list">
            <!-- emoji list will be generated here -->
          </div>
          <div id="emoji-categories">
            <span class="emoji-category sender-hover selected" data-category="faces" data-title="faces">😊</span>
            <span class="emoji-category sender-hover" data-category="body" data-title="heads & body">🖐️</span>
            <span class="emoji-category sender-hover" data-category="objects" data-title="objects">📦</span>
            <span class="emoji-category sender-hover" data-category="other" data-title="other">❓</span>
          </div>
          <div class="info-icon" title="Info">
            <span>i</span>
          </div>
          <div class="info-window">
            <p>You can use these HTML tags:</p>
            <p><b>&lt;b&gt;&lt;i&gt;&lt;u&gt;&lt;mark&gt;</b></p>
            <hr>
            <p>Use <kbd>Shift</kbd> + <kbd>Enter</kbd> to make a new line</p>
          </div>
        </div>
        <textarea id="message-input" name="message" placeholder="Type your message.." title="" autocomplete="off" rows="2" wrap="soft" minlength="1" maxlength="2048" required></textarea>
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken;?>" autocomplete="off">
        <input type="hidden" name="nonce" value="<?php echo $nonce;?>" autocomplete="off">
        <input type="submit" value="Send" id="submit-btn" class="button3">
      </form>
    </div>
    <footer>Copyleft</footer>
  </div>
  </body>
</html>

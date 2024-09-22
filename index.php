<?php
  include_once("config.php");
  session_start();
  session_regenerate_id(true);

  if (isset($_SESSION['UserID'])) {
    clearHtmlCache();
    unset($_SESSION['UserID']);
  }
  // Generate a unique user ID
  $userId = bin2hex(random_bytes(5));
  $_SESSION['UserID'] = $userId;

  if (isset($_SESSION['ChatCode'])) {
    clearHtmlCache();
    unset($_SESSION['ChatCode']);
  }
  
  // Generate a unique chat code
  $chatCode = uniqid('', false);
  $_SESSION['ChatCode'] = $chatCode;

  // Dynamic csrf token
  $CFStoken = bin2hex(random_bytes(32));
  $_SESSION['csrf_token_start'] = $CFStoken;

  // CSP
  $nonce = generateNonce();
  $csp = "base-uri 'self'";
  $csp .= "; default-src 'self' 'nonce-" . $nonce . "'";
  $csp .= "; script-src 'strict-dynamic' 'nonce-" . $nonce . "'";
  $csp .= "; style-src 'self' 'unsafe-inline'";
  $csp .= "; img-src 'self' data: https:";
  $csp .= "; connect-src 'self'";
  $csp .= "; form-action 'self' 'nonce-" . $nonce . "'";
  $csp .= "; object-src 'none'";
  $csp .= "; worker-src 'self' 'nonce-" . $nonce . "' 'unsafe-inline'";
  $csp .= "; upgrade-insecure-requests";
  header("Content-Security-Policy: " . $csp);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Secure Chat</title>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="public, max-age=31536000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="<?php echo $csp;?>">
    <link rel="stylesheet" type="text/css" as="style" media="all" href="style.css" nonce="<?php echo $nonce;?>" crossorigin="use-credentials">
    <script type="application/javascript" src="js/start.js" media="all" crossorigin="use-credentials" nonce="<?php echo $nonce; ?>" defer></script>
  <body>
    <div id="container">
      <div id="start-box">
        <h1>Welcome to FastChat</h1>
        <h2>Create or Join a Chat Room</h2>
        <form action="chatroom.php" method="post" autocomplete="off" id="start-form">
          <button id="fillForm" class="button3 sender-hover" data-title="Insert chatroom ID"><span>>></span></button>
          <span class="sender-hover" data-title="Only Letters and Numbers. Must be 13 characters long. Letters are case sensitive.">
            <input type="text" id="chatCode" name="chatCode" placeholder="Enter chatroom ID" minlength="13" maxlength="13" pattern="[A-Za-z0-9]{13}" title="" required>
          </span>
          <input type="hidden" name="userId" value="<?php echo $userId; ?>" autocomplete="off">
          <input type="hidden" name="csrf_token_start" value="<?php echo $CFStoken; ?>" autocomplete="off">
          <input type="submit" value="Join Chat" id="submit-btn" class="button3">
        </form>
        <p class="id-code">Your User ID: <span class="id-text"><?php echo $userId; ?></span></p>
        <p class="share-code">Chatroom ID: <?php echo '<span class="id-text" id="chatCodeID" data-chat-code="'.$chatCode.'">'.$chatCode.'</span>'  ?></p>
        <footer>Copyleft</footer>
      </div>
    </div>
  </body>
</html>
</html>
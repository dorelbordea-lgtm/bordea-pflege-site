<?php
// send_contact.php – Quick contact form sender (SMTP preferred, fallback mail())

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

// Honeypot
if (!empty($_POST['hpcheck'])) {
  header('Location: success.html');
  exit;
}

// Simple timestamp check (>=3s)
$now = time()*1000;
$ts = isset($_POST['ts']) ? (int)$_POST['ts'] : 0;
if ($ts && ($now - $ts) < 3000) {
  http_response_code(429);
  echo 'Bitte erneut versuchen.';
  exit;
}

function field($name){
  if(!isset($_POST[$name])) return '';
  return trim(is_array($_POST[$name]) ? implode(', ', $_POST[$name]) : $_POST[$name]);
}

$name = field('name');
$email = field('email');
$message = field('message');

$errors = [];
if($name==='') $errors[]='Name';
if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[]='E‑Mail';
if($message==='') $errors[]='Nachricht';

if($errors){
  http_response_code(422);
  echo 'Bitte prüfen: ' . implode(', ', $errors);
  exit;
}

$subject = 'Neue Kontaktanfrage – ' . $name;
$body = "Kontaktanfrage:\n\nName: {$name}\nE‑Mail: {$email}\n\nNachricht:\n{$message}\n";

// Load config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) $configPath = __DIR__ . '/config.sample.php';
$config = require $configPath;
$to   = $config['to']   ?? 'fachkraft@bordea-pflege.de';
$from = $config['from'] ?? 'no-reply@bordea-pflege.de';

// SMTP over SSL:465
function smtp_send_ssl($host, $port, $user, $pass, $from, $to, $subject, $body, $reply){
  $fp = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15);
  if (!$fp) return false;
  $read = function() use ($fp){ return fgets($fp, 515); };
  $cmd = function($c) use ($fp){ fwrite($fp, $c."\r\n"); };

  $expect = function($prefixes) use ($read){
    $line = $read();
    if ($line === false) return false;
    foreach((array)$prefixes as $p){ if (strpos($line, $p) === 0) return true; }
    return false;
  };

  if (!$expect(['220'])) { fclose($fp); return false; }
  $cmd("EHLO bordea-pflege.de");
  if (!$expect(['250'])) { fclose($fp); return false; }

  $cmd("AUTH LOGIN");
  if (!$expect(['334'])) { fclose($fp); return false; }
  $cmd(base64_encode($user));
  if (!$expect(['334'])) { fclose($fp); return false; }
  $cmd(base64_encode($pass));
  if (!$expect(['235'])) { fclose($fp); return false; }

  $cmd("MAIL FROM:<$from>");
  if (!$expect(['250'])) { fclose($fp); return false; }
  $cmd("RCPT TO:<$to>");
  if (!$expect(['250','251'])) { fclose($fp); return false; }
  $cmd("DATA");
  if (!$expect(['354'])) { fclose($fp); return false; }

  $headers = [];
  $headers[] = "From: Website <{$from}>";
  $headers[] = "Reply-To: {$reply}";
  $headers[] = "To: {$to}";
  $headers[] = "Subject: {$subject}";
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";
  $headers[] = "Content-Transfer-Encoding: 8bit";

  $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
  $cmd($msg);
  if (!$expect(['250'])) { fclose($fp); return false; }

  $cmd("QUIT");
  fclose($fp);
  return true;
}

$sent = false;
if (!empty($config['smtp']['enable'])) {
  $host = $config['smtp']['host'] ?? 'smtp.hostinger.com';
  $port = (int)($config['smtp']['port'] ?? 465);
  $user = $config['smtp']['user'] ?? '';
  $pass = $config['smtp']['pass'] ?? '';
  if ($user && $pass) {
    $sent = smtp_send_ssl($host, $port, $user, $pass, $from, $to, $subject, $body, $email);
  }
}

if (!$sent) {
  $headers = "From: Website <{$from}>\r\n";
  $headers .= "Reply-To: {$email}\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $sent = @mail($to, $subject, $body, $headers);
}

if ($sent) {
  header('Location: success.html');
  exit;
} else {
  http_response_code(500);
  echo 'Nachricht konnte nicht gesendet werden. Bitte senden Sie direkt an ' . htmlspecialchars($to) . ' oder per WhatsApp.';
}

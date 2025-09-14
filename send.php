<?php
// send.php – Versendet Anfragen vom Buchen-Formular.
// Bevorzugt SMTP (SSL auf 465) per einfacher nativer Implementierung.
// Fällt zurück auf mail() wenn SMTP deaktiviert ist oder fehlschlägt.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

// Honeypot
// Security: sanitize header injection
foreach($_POST as $k=>$v){
  if(preg_match('/(content-type|bcc:|cc:|to:)/i', $v)){
    http_response_code(400);
    exit('Invalid input detected.');
  }
}

if (!empty($_POST['hpcheck'])) {
  header('Location: success.html');
  exit;
}

function field($name){
  if(!isset($_POST[$name])) return '';
  $v = $_POST[$name];
  if (is_array($v)) return trim(implode(', ', $v));
  return trim($v);
}

// Collect
$vorname    = field('vorname');
$nachname   = field('nachname');
$einrichtung= field('einrichtung');
$rolle      = field('rolle');
$email      = field('email');
$telefon    = field('telefon');
$einsatzort = field('einsatzort');
$beginn     = field('beginn');
$ende       = field('ende');
$schicht    = field('schicht');
$honorar    = field('honorar');
$nachricht  = field('nachricht');

// Validate
$errors = [];
if ($vorname === '') $errors[] = 'Vorname';
if ($nachname === '') $errors[] = 'Nachname';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E‑Mail';
if ($telefon === '') $errors[] = 'Telefon';
if ($einsatzort === '') $errors[] = 'Einsatzort';
if ($beginn === '') $errors[] = 'Einsatzbeginn';

if ($errors) {
  http_response_code(422);
  echo 'Bitte prüfen: ' . implode(', ', $errors);
  exit;
}

$subject = 'Neue Buchungsanfrage – ' . $vorname . ' ' . $nachname;
$bodyLines = [
  "Buchungsanfrage über bordea-pflege.de",
  "",
  "Vorname: $vorname",
  "Nachname: $nachname",
  "Einrichtung: $einrichtung",
  "Rolle: " . ($rolle ?: '-'),
  "E‑Mail: $email",
  "Telefon: $telefon",
  "Einsatzort: $einsatzort",
  "Beginn: $beginn",
  "Ende: " . ($ende ?: '-'),
  "Schicht: " . ($schicht ?: '-'),
  "Budget: " . ($honorar ?: '-'),
  "Nachricht: " . ($nachricht ?: '-') 
];
$body = implode("\n", $bodyLines);

// Load config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
  $configPath = __DIR__ . '/config.sample.php';
}
$config = require $configPath;
$to   = $config['to']   ?? 'fachkraft@bordea-pflege.de';
$from = $config['from'] ?? 'no-reply@bordea-pflege.de';

// SMTP sender (SSL:465)
function smtp_send_ssl($host, $port, $user, $pass, $from, $to, $subject, $body) {
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
  $headers[] = "Reply-To: {$from}";
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

// Try SMTP if enabled
$sent = false;
if (!empty($config['smtp']['enable'])) {
  $host = $config['smtp']['host'] ?? 'smtp.hostinger.com';
  $port = (int)($config['smtp']['port'] ?? 465);
  $user = $config['smtp']['user'] ?? '';
  $pass = $config['smtp']['pass'] ?? '';
  if ($user && $pass) {
    $sent = smtp_send_ssl($host, $port, $user, $pass, $from, $to, $subject, $body);
  }
}

// Fallback to mail()
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

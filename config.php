<?php
// Kopiere diese Datei zu config.php und trage deine SMTP-Daten ein.
return [
  'smtp' => [
    'enable' => true,                 // true = SMTP benutzen, false = PHP mail()
    'host'   => 'smtp.hostinger.com', // z.B. smtp.hostinger.com
    'port'   => 465,                  // 465 (SSL) oder 587 (STARTTLS - hier nicht unterstützt)
    'secure' => 'ssl',                // 'ssl' für Port 465
    'user'   => 'fachkraft@bordea-pflege.de',
    'pass'   => 'PAROLA_DE_SCHIMBAT'
  ],
  'to'   => 'fachkraft@bordea-pflege.de',
  'from' => 'no-reply@bordea-pflege.de'
];

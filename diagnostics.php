<?php
echo "<h2>Mail‑Diagnose</h2>";
$cfg = @include __DIR__ . '/config.php';
if (!$cfg) $cfg = @include __DIR__ . '/config.sample.php';
echo "<pre>";
print_r($cfg);
echo "</pre>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>OpenSSL: " . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'n/a') . "</p>";
?>
<p>Testează trimiterea completând formularul din <a href="buchen.html">buchen.html</a>.</p>

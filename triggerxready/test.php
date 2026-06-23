<?php
// Test basico - si esto funciona, el problema es el config.php o htaccess
echo '<h1 style="color:green">PHP funciona correctamente</h1>';
echo '<p>PHP Version: ' . PHP_VERSION . '</p>';
echo '<p>Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</p>';
echo '<p>Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '</p>';
echo '<p>Script Name: ' . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . '</p>';
echo '<p>Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . '</p>';
echo '<hr>';
echo '<p><a href="config_test.php">→ Test config.php</a></p>';

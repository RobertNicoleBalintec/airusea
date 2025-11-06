<?php
function logEvent($message) {
    $logFile = __DIR__ . '/log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
?>

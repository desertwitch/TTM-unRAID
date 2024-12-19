<?php
/* Copyright Derek Macias (parts of code from NUT package)
 * Copyright macester (parts of code from NUT package)
 * Copyright gfjardim (parts of code from NUT package)
 * Copyright SimonF (parts of code from NUT package)
 * Copyright Dan Landon (parts of code from Web GUI)
 * Copyright Bergware International (parts of code from Web GUI)
 * Copyright Lime Technology (any and all other parts of Unraid)
 *
 * Copyright desertwitch (as author and maintainer of this file)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 */
header('Content-Type: application/json');

if (!isset($_GET['session']) || !preg_match('/^[a-zA-Z0-9_\-\$]+$/', $_GET['session'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing session ID.'
    ]);
    exit;
}

$sessionID = escapeshellarg($_GET['session']);

$command = "tmux kill-session -t {$sessionID} 2>&1";
$output = [];
$returnCode = null;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => implode("\n", $output)
    ]);
}
exit;
?>

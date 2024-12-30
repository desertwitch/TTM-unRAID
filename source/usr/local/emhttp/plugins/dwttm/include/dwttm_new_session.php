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
require_once '/usr/local/emhttp/plugins/dwttm/include/dwttm_helpers.php';
try {
    header('Content-Type: application/json');

    if (isset($_GET['session']) && !preg_match('/^[a-zA-Z0-9_\-\$]+$/', $_GET['session'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid session ID.'
        ]);
        exit;
    }

    $session = isset($_GET['session']) ? escapeshellarg($_GET['session']) : null;

    $command = $session
        ? "tmux new-session -s $session -d -x 80 -y 24 -P -F '#{session_id}' 'env LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 TERM=xterm-256color /bin/bash'"
        : "tmux new-session -d -x 80 -y 24 -P -F '#{session_id}' 'env LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 TERM=xterm-256color /bin/bash'";

    $result = dwttm_executeCommand($command);

    if ($result['returnCode'] === 0 && !empty($result['stdout'])) {
        echo json_encode([
            'success' => true,
            'session_id' => $result['stdout']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['stderr'] ?: 'Non-zero return code.'
        ]);
    }
    exit;
} catch (\Throwable $t) {
    error_log($t);
    echo json_encode([
        'success' => false,
        'error' => $t->getMessage()
    ]);
    exit;
} catch (\Exception $e) {
    error_log($e);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>

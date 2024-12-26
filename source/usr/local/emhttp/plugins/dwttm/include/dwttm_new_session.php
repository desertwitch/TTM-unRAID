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

    if ($session) {
        $command = "tmux new-session -s $session -d -x 80 -y 24 -P -F '#{session_id}' 'env TERM=xterm-256color /bin/bash'";
    } else {
        $command = "tmux new-session -d -x 80 -y 24 -P -F '#{session_id}' 'env TERM=xterm-256color /bin/bash'";
    }

    $descriptorspec = [
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);

        if ($returnCode === 0 && !empty($stdout)) {
            echo json_encode([
                'success' => true,
                'session_id' => trim($stdout)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => trim($stderr ?? "") ?: 'Non-zero return code.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to execute tmux command.'
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

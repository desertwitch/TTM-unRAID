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

    $command = 'tmux list-sessions -F "#{session_id}/#{session_name}/#{session_created}" 2>/dev/null';
    $output = [];
    $returnCode = null;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        echo json_encode([
            "success" => false,
            "response" => []
        ]);
        exit;
    }

    $response = [];

    foreach ($output as $line) {
        list($sessionId, $sessionName, $sessionCreated) = explode('/', $line, 3);

        $captureCommand = "tmux capture-pane -t '{$sessionId}:0' -p 2>/dev/null";
        $captureOutput = [];
        $captureReturnCode = 0;
        exec($captureCommand, $captureOutput, $captureReturnCode);

        $previewSuccess = ($captureReturnCode === 0);
        $preview = $previewSuccess ? implode("\n", $captureOutput) : "Failed to retrieve preview for session {$sessionName}.";

        $response[] = [
            "session_id" => $sessionId,
            "session_name" => $sessionName,
            "created_at" => date('Y-m-d H:i:s', intval($sessionCreated)),
            "preview" => $preview,
            "preview_success" => $previewSuccess
        ];
    }

    echo json_encode([
        "success" => true,
        "response" => $response
    ]);
    exit;
} catch(\Throwable $t) {
    error_log($t);
    echo json_encode([
        'success' => false,
        'error' => $t->getMessage();
    ]);
    exit;
} catch(\Exception $e) {
    error_log($e);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage();
    ]);
    exit;
}
?>

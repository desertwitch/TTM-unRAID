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

$command = 'tmux list-sessions -F "#{session_id}/#{session_name}/#{session_created}"';
$output = [];
$returnCode = null;
exec($command . " 2>&1", $output, $returnCode);

if ($returnCode !== 0) {
    $outputString = implode("\n", $output);
    if (strpos($outputString, 'no server running') !== false) {
        echo json_encode([
            "success" => true,
            "message" => "No active tmux sessions.",
            "response" => []
        ]);
        exit;
    }
    else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to retrieve tmux sessions.",
            "error" => $outputString
            "response" => []
        ]);
        exit;
    }
}

$response = [];

foreach ($output as $line) {
    list($sessionId, $sessionName, $sessionCreated) = explode('/', $line, 3);

    $captureCommand = "tmux capture-pane -t '{$sessionId}:0' -p";
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
?>

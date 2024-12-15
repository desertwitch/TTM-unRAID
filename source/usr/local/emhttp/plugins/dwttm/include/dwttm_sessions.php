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
$command = 'tmux list-sessions -F "#{session_name} #{session_created}"';
$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve tmux sessions."
    ]);
    exit;
}

$response = [];

foreach ($output as $line) {
    // Split the output line into session name and creation time
    list($sessionName, $sessionCreated) = explode(' ', $line, 2);

    // Get only the visible lines of the session's active pane
    $captureCommand = "tmux capture-pane -t {$sessionName} -p";
    $captureOutput = [];
    $captureReturnCode = 0;
    exec($captureCommand, $captureOutput, $captureReturnCode);

    $previewSuccess = ($captureReturnCode === 0);
    $preview = $previewSuccess ? implode("\n", $captureOutput) : "Failed to retrieve preview for session {$sessionName}.";

    $response[] = [
        "session_name" => $sessionName,
        "created_at" => date('Y-m-d H:i:s', intval($sessionCreated)), // Convert UNIX timestamp to readable format
        "preview" => $preview,
        "preview_success" => $previewSuccess
    ];
}

echo json_encode([
    "success" => true,
    "response" => $response
]);
?>

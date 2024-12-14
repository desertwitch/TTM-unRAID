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

$command = 'tmux list-sessions -F "#{session_name}"';

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

foreach ($output as $session) {
    // Fetch preview of the tmux session
    $previewCommand = "tmux capture-pane -t {$session} -pS -10";
    $previewOutput = [];
    $previewReturnCode = 0;
    exec($previewCommand, $previewOutput, $previewReturnCode);

    $previewSuccess = ($previewReturnCode === 0);
    $preview = $previewSuccess ? implode("\n", $previewOutput) : "Failed to retrieve preview for session {$session}.";

    $response[] = [
        "session_name" => $session,
        "preview" => $preview,
        "preview_success" => $previewSuccess
    ];
}

echo json_encode([
    "success" => true,
    "response" => $response
]);
?>

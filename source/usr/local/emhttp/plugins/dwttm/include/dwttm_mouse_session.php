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

    if (!isset($_GET['session']) || !preg_match('/^[a-zA-Z0-9_\-\$]+$/', $_GET['session'])) {
        echo json_encode([
            'error' => 'Invalid or missing session ID.'
        ]);
        exit;
    }

    if (isset($_GET['mouse']) && !preg_match('/^[a-zA-Z0-9_\-\$]+$/', $_GET['mouse'])) {
        echo json_encode([
            'error' => 'Invalid mouse state providd.'
        ]);
        exit;
    }

    $session = isset($_GET['session']) ? escapeshellarg($_GET['session']) : null;
    $mouseState = isset($_GET['mouse']) ? strtolower($_GET['mouse']) : null;

    $currentMouseStateCommand = "tmux display -p -t $session:0 '#{pane_in_mode}'";
    $result = dwttm_executeCommand($currentMouseStateCommand);

    if ($result['returnCode'] !== 0) {
        echo json_encode(['error' => $result['stderr'] ?: 'Failed to retrieve tmux mouse state.']);
        exit;
    }

    $currentMouseState = strpos($result['stdout'], "1") !== false ? "on" : "off";

    if ($mouseState === null) {
        echo json_encode(["mouse" => $currentMouseState]);
        exit;
    } else {
        if (!in_array($mouseState, ["on", "off"])) {
            echo json_encode(["error" => "Invalid mouse state. Use 'on' or 'off'."]);
            exit;
        }

        if ($mouseState === "off" && $currentMouseState === "on") {
            $exitCopyModeCommand = "tmux send-keys -t $session:0 q";
            $result = dwttm_executeCommand($exitCopyModeCommand);

            if ($result['returnCode'] !== 0) {
                echo json_encode(['error' => $result['stderr'] ?: 'Failed to leave mouse mode.']);
                exit;
            }
        } elseif ($mouseState === "on" && $currentMouseState === "off") {
            $toggleMouseCommand = "tmux copy-mode -t $session:0";
            $result = dwttm_executeCommand($toggleMouseCommand);

            if ($result['returnCode'] !== 0) {
                echo json_encode(['error' => $result['stderr'] ?: 'Failed to enter mouse mode.']);
                exit;
            }
        }

        $newMouseStateCommand = "tmux display -p -t $session:0 '#{pane_in_mode}'";
        $result = dwttm_executeCommand($newMouseStateCommand);

        if ($result['returnCode'] !== 0) {
            echo json_encode(['error' => $result['stderr'] ?: 'Failed to retrieve tmux mouse state after change.']);
            exit;
        }

        $newMouseState = strpos($result['stdout'], "1") !== false ? "on" : "off";

        echo json_encode(["oldmouse" => $currentMouseState, "requestmouse" => $mouseState, "newmouse" => $newMouseState]);
        exit;
    }
} catch(\Throwable $t) {
    error_log($t);
    echo json_encode([
        'error' => $t->getMessage()
    ]);
    exit;
} catch(\Exception $e) {
    error_log($e);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
}
?>

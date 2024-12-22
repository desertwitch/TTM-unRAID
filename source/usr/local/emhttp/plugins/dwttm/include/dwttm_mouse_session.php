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

    $currentMouseStateCommand = "tmux display -p -t $session:0 \"#{pane_in_mode}\" 2>/dev/null";
    $currentMouseStateOutput = [];
    $currentMouseStateReturnVar = null;

    exec($currentMouseStateCommand, $currentMouseStateOutput, $currentMouseStateReturnVar);

    if ($currentMouseStateReturnVar !== 0) {
        echo json_encode(["error" => "Failed to retrieve tmux mouse state"]);
        exit;
    }
    $currentMouseState = strpos(implode("\n", $currentMouseStateOutput), "1") !== false ? "on" : "off";

    if ($mouseState === null) {
        echo json_encode(["mouse" => $currentMouseState]);
        exit;
    } else {
        if (!in_array($mouseState, ["on", "off"])) {
            echo json_encode(["error" => "Invalid mouse state. Use 'on' or 'off'."]);
            exit;
        }

        if ($mouseState === "off") {
            if($currentMouseState === "on") {
                $exitCopyModeCommand = "tmux send-keys -t $session:0 q 2>/dev/null";
                $exitCopyModeOutput = [];
                $exitCopyModeReturnVar = null;
                exec($exitCopyModeCommand, $exitCopyModeOutput, $exitCopyModeReturnVar);

                if($exitCopyModeReturnVar !== 0) {
                    echo json_encode(["error" => "Failed to leave mouse mode."]);
                    exit;
                }
            }
        } else {
            if($currentMouseState === "off") {
                $toggleMouseCommand = "tmux copy-mode -t $session:0 2>/dev/null";
                $toggleOutput = [];
                $toggleReturnVar = null;
                exec($toggleMouseCommand, $toggleOutput, $toggleReturnVar);

                if ($toggleReturnVar !== 0) {
                    echo json_encode(["error" => "Failed to enter mouse mode."]);
                    exit;
                }
            }
        }

        $newMouseStateCommand = "tmux display -p -t $session:0 \"#{pane_in_mode}\" 2>/dev/null";
        $newMouseStateOutput = [];
        $newMouseStateReturnVar = null;
        exec($newMouseStateCommand, $newMouseStateOutput, $newMouseStateReturnVar);

        if ($newMouseStateReturnVar !== 0) {
            echo json_encode(["error" => "Failed to retrieve tmux mouse state after change"]);
            exit;
        }

        $newMouseState = strpos(implode("\n", $newMouseStateOutput), "1") !== false ? "on" : "off";

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

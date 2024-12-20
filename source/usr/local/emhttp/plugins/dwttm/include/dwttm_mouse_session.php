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

$session = isset($_GET['session']) ? escapeshellarg($_GET['session']) : null;
$mouseState = isset($_GET['mouse']) ? strtolower($_GET['mouse']) : null;

if (!$session) {
    echo json_encode(["error" => "Session parameter is required"]);
    exit;
}

$currentMouseStateCommand = "tmux show-option -t $session:0 mouse 2>/dev/null";
$currentMouseStateOutput = [];
$currentMouseStateReturnVar = null;

exec($currentMouseStateCommand, $currentMouseStateOutput, $currentMouseStateReturnVar);

if ($currentMouseStateReturnVar !== 0) {
    echo json_encode(["error" => "Failed to retrieve tmux mouse state"]);
    exit;
}
$currentMouseState = strpos(implode("\n", $currentMouseStateOutput), "on") !== false ? "on" : "off";

if ($mouseState === null) {
    echo json_encode(["mouse" => $currentMouseState]);
    exit;
} else {
    if (!in_array($mouseState, ["on", "off"])) {
        echo json_encode(["error" => "Invalid mouse state. Use 'on' or 'off'."]);
        exit;
    }

    $toggleMouseCommand = "tmux set-option -t $session:0 mouse $mouseState 2>/dev/null";
    $toggleOutput = [];
    $toggleReturnVar = null;
    exec($toggleMouseCommand, $toggleOutput, $toggleReturnVar);

    if ($toggleReturnVar !== 0) {
        echo json_encode(["error" => "Failed to toggle tmux mouse state"]);
        exit;
    }

    $leaveMode = false;
    $leaveModeSuccess = false;

    if ($mouseState === "off") {
        $checkCopyModeCommand = "tmux display -p -t $session:0 \"#{pane_in_mode}\" 2>/dev/null";
        $checkCopyModeOutput = [];
        $checkCopyModeReturnVar = null;
        exec($checkCopyModeCommand, $checkCopyModeOutput, $checkCopyModeReturnVar);

        if ($checkCopyModeReturnVar === 0 && !empty($checkCopyModeOutput) && strpos(implode("\n", $checkCopyModeOutput), "1") !== false) {
            $leaveMode = true;
            $exitCopyModeCommand = "tmux send-keys -t $session:0 q 2>/dev/null";
            $exitCopyModeOutput = [];
            $exitCopyModeReturnVar = null;
            exec($exitCopyModeCommand, $exitCopyModeOutput, $exitCopyModeReturnVar);

            if($exitCopyModeReturnVar === 0) {
                $leaveModeSuccess = true;
            }
        }
    }

    $newMouseStateCommand = "tmux show-option -t $session:0 mouse 2>/dev/null";
    $newMouseStateOutput = [];
    $newMouseStateReturnVar = null;
    exec($newMouseStateCommand, $newMouseStateOutput, $newMouseStateReturnVar);

    if ($newMouseStateReturnVar !== 0) {
        echo json_encode(["error" => "Failed to retrieve tmux mouse state after change"]);
        exit;
    }

    $newMouseState = strpos(implode("\n", $newMouseStateOutput), "on") !== false ? "on" : "off";

    if($leaveMode === true) {
        echo json_encode(["oldmouse" => $currentMouseState, "requestmouse" => $mouseState, "newmouse" => $newMouseState, "leaveMode" => $leaveMode, "leaveModeSucess" => $leaveModeSuccess]);
        exit;
    } else {
        echo json_encode(["oldmouse" => $currentMouseState, "requestmouse" => $mouseState, "newmouse" => $newMouseState]);
        exit;
    }
}
?>

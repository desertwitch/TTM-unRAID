<?php
header('Content-Type: application/json');

$session = isset($_GET['session']) ? escapeshellarg($_GET['session']) : null;

if (!$session) {
    http_response_code(400);
    echo json_encode(["error" => "Session parameter is required"]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'fetch';

if ($action === 'fetch') {
    // Check the current mouse status for the session
    $output = shell_exec("tmux show-options -t $session -g mouse 2>&1");
    if (strpos($output, 'on') !== false) {
        echo json_encode(["mouse" => "on"]);
    } elseif (strpos($output, 'off') !== false) {
        echo json_encode(["mouse" => "off"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Unable to fetch mouse status"]);
    }
} elseif ($action === 'toggle') {
    // Toggle the mouse status
    $currentStatus = shell_exec("tmux show-options -t $session -g mouse 2>&1");
    $newStatus = (strpos($currentStatus, 'on') !== false) ? 'off' : 'on';

    shell_exec("tmux set-option -t $session -g mouse $newStatus");
    echo json_encode(["mouse" => $newStatus]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid action"]);
}
?>


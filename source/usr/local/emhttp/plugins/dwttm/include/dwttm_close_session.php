<?php
header('Content-Type: application/json');

// Check if the session variable is provided via GET
if (!isset($_GET['session']) || empty($_GET['session'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No session ID provided.'
    ]);
    exit;
}

$sessionID = escapeshellarg($_GET['session']); // Sanitize input

// Kill the tmux session (regardless of existence)
$killCommand = "tmux kill-session -t {$sessionID} 2>/dev/null";

exec($killCommand, $nullOutput, $returnVar);

// Check the result of the kill command
if ($returnVar === 0) {
    // Success: Session closed
    echo json_encode([
        'success' => true,
        'message' => "Session {$sessionID} has been successfully closed."
    ]);
} else {
    // Failure to close the session (could be due to invalid ID or other issues)
    echo json_encode([
        'success' => false,
        'message' => "Failed to close session {$sessionID}. It may not exist."
    ]);
}
exit;
?>

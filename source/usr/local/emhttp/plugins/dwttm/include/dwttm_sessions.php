<?php
// Command to get the tmux session names
$command = 'tmux list-sessions -F "#{session_name}"';

// Execute the command
$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

// Check if the command was successful
if ($returnCode !== 0) {
    // Command failed, return an error response
    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve tmux sessions."
    ]);
    exit;
}

// Structure the response
$response = [];
foreach ($output as $session) {
    $response[] = $session;
}

echo json_encode([
    "success" => true,
    "response" => $response
]);
?>

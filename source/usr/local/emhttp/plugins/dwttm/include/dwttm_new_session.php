<?php
header('Content-Type: application/json');

// Path to the custom tmux config file
$tmuxConfig = '/etc/ttmd.conf';

// TMUX command with the custom configuration file
$command = "tmux -f {$tmuxConfig} new-session -d -P -F \"#{session_id}\"";

// Execute the command and capture the output
$output = null;
$returnVar = null;
exec($command, $output, $returnVar);

// Check if the command was successful and return JSON response
if ($returnVar === 0 && !empty($output)) {
    echo json_encode([
        'success' => true,
        'session_id' => $output[0]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create a new tmux session.'
    ]);
}
?>

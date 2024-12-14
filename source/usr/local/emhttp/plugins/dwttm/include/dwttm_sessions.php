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
     // Get the number of visible lines in the session's pane
     $paneHeightCommand = "tmux display -p -t {$session}:0 '#{pane_height}'";
     $paneHeightOutput = [];
     exec($paneHeightCommand, $paneHeightOutput, $paneHeightReturnCode);
 
     if ($paneHeightReturnCode !== 0 || empty($paneHeightOutput)) {
         $response[] = [
             "session_name" => $session,
             "preview" => "Failed to determine pane height for session {$session}.",
             "preview_success" => false
         ];
         continue;
     }
 
     $paneHeight = intval($paneHeightOutput[0]);
 
     // Capture only the visible screen of the session
     $captureCommand = "tmux capture-pane -t {$session}:0 -p";
     $captureOutput = [];
     exec($captureCommand, $captureOutput, $captureReturnCode);
 
     $previewSuccess = ($captureReturnCode === 0);
     $preview = $previewSuccess ? implode("\n", array_slice($captureOutput, -$paneHeight)) : "Failed to retrieve preview for session {$session}.";
 
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

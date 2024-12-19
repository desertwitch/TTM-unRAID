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

$command = "tmux new-session -d -x 80 -y 24 -P -F '#{session_id}' 'env TERM=xterm /bin/bash'";
$output = [];
$returnCode = null;
exec($command, $output, $returnCode);

if ($returnCode === 0 && !empty($output)) {
    echo json_encode([
        'success' => true,
        'session_id' => trim($output[0])
    ]);
} else {
    echo json_encode([
        'success' => false
    ]);
}
exit;
?>

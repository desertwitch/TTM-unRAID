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
function dwttm_executeCommand($command) {
    $descriptorspec = [
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        try {
            $stdout = stream_get_contents($pipes[1]) ?? "";
            $stderr = stream_get_contents($pipes[2]) ?? "";
        } finally {
            if (isset($pipes[1]) && is_resource($pipes[1])) fclose($pipes[1]);
            if (isset($pipes[2]) && is_resource($pipes[2])) fclose($pipes[2]);
            $returnCode = proc_close($process);
        }

        return ['stdout' => trim($stdout ?? ''), 'stderr' => trim($stderr ?? ''), 'returnCode' => ($returnCode ?? -1)];
    }

    return ['stdout' => '', 'stderr' => 'Failed to open process', 'returnCode' => -1];
}
?>

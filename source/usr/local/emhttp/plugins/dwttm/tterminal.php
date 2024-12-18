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
if (!function_exists('autov')) {
    function autov($file, $ret = false) {
        $docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
        $path = $docroot . $file;
        clearstatcache(true, $path);
        $time = file_exists($path) ? filemtime($path) : 'autov_fileDoesntExist';
        $newFile = "$file?v=" . $time;
        
        if ($ret) {
            return $newFile;
        } else {
            echo $newFile;
        }
    }
}

$currentSession = isset($_GET['session']) ? $_GET['session'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTerminal</title>
    <script src="<?=autov('/plugins/dwttm/js/xterm.js');?>"></script>
    <script src="<?=autov('/plugins/dwttm/js/addon-fit.js');?>"></script>
    <link type="text/css" rel="stylesheet" href="<?=autov('/plugins/dwttm/css/xterm.css');?>">
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            font-family: Arial, sans-serif;
            background-color: #000;
            box-sizing: border-box;
            overflow: hidden;
        }

        #session-dropdown {
            width: 100%;
            height: 40px;
            font-size: 16px;
            border: none;
            outline: none;
            padding-left: 10px;
            padding-right: 10px;
            background-color: #333;
            color: #fff;
        }

        #terminal-container {
            width: 100%;
            height: 100%; 
            padding: 10px;
            overflow: hidden;
        }

        .xterm .xterm-viewport {
            overflow: hidden;
        }

        .plus-icon {
            font-size: 50px;
            color: white;
            margin-bottom: 10px;
        }

        .new-session-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100vw;
            height: 100vh;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .new-session-container:hover {
            background-color: #222;
        }

        .new-session-text {
            font-size: 16px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
        }
</style>
</head>
<body>
    <select id="session-dropdown"></select>

    <div id="content">
        <?php if (!$currentSession): ?>
            <div class="new-session-container" id="new-session-container" onclick="createNewSession()">
                <div class="plus-icon">+</div>
                <div class="new-session-text">New Session</div>
            </div>
        <?php else: ?>
            <div id="terminal-container"></div>
        <?php endif; ?>
    </div>

    <script>
        const ttimers = {};
        const term = new Terminal({ scrollback: 0, cursorBlink: true });
        const fitAddon = new FitAddon.FitAddon();
        const dropdown = document.getElementById('session-dropdown');

        function fetchSessions() {
        // CHECKED - OK 
            fetch('/plugins/dwttm/include/dwttm_sessions.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const sessions = data.response;

                        const currentSession = <?= json_encode($currentSession); ?>;

                        sessions.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                        const dropdown = document.getElementById('session-dropdown');

                        dropdown.innerHTML = '<option value="">Select a session</option>';

                        sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session.session_id;
                            option.textContent = session.session_name;
                            dropdown.appendChild(option);

                            if (session.session_id === currentSession) {
                                option.selected = true; 
                                document.title = `${session.session_name}: TTerminal`;
                            }
                        });
                    } else {
                        console.error('Failed to fetch sessions:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching sessions:', error);
                });
                clearTimeout(ttimers.fetchSessions);
                ttimers.fetchSessions = setTimeout(fetchSessions, 3000);
        }

        function connectToSession(session) {
        // CHECKED - OK
            if (!session) {
                const urlWithoutParams = window.location.origin + window.location.pathname;
                window.location.href = urlWithoutParams;
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('session', session);
            window.location.search = urlParams.toString();
        }

        function createNewSession() {
        // CHECKED - OK
            fetch('/plugins/dwttm/include/dwttm_new_session.php', {
                method: 'GET',
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    connectToSession(data.session_id);
                } else {
                    alert('Failed to create a new session.');
                }
            })
            .catch(error => console.error('Error creating session:', error));
        }

        document.addEventListener('DOMContentLoaded', () => {
        // CHECKED - OK
            <?php if ($currentSession): ?>
            const terminalContainer = document.getElementById('terminal-container');

            term.loadAddon(fitAddon);
            term.open(terminalContainer);
            fitAddon.fit();

            const currentSession = <?= json_encode($currentSession); ?>;
            
            const wsUrl = `ws://${window.location.hostname}:3000/ws?session=${encodeURIComponent(currentSession)}`;
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                term.clear();
            };

            ws.onmessage = (event) => {
                term.write(event.data);
            };

            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };

            ws.onclose = () => {
                term.write('\r\n*** Disconnected from session ***\r\n');
            };

            term.onData((data) => {
                if (ws.readyState === WebSocket.OPEN) {
                    ws.send(data);
                }
            });

            term.focus();
            <?php endif; ?>
            fetchSessions();
        });
        
        document.addEventListener('change', (event) => {
        // CHECKED - OK
            if (event.target && event.target.id === 'session-dropdown') {
                const selectedSession = event.target.value;
                connectToSession(selectedSession);
            }
        });
    </script>
</body>
</html>

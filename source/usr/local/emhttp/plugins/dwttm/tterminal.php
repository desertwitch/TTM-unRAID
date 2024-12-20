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
require_once '/usr/local/emhttp/plugins/dwttm/include/dwttm_config.php';

if($dwttm_running && !$dwttm_tmux_functional) {
    @shell_exec("/etc/rc.d/rc.ttmd stop &>/dev/null");
    echo("<b>Error: Tmux not found or not functional.</b><br>");
    echo("<b>Error: Please refer to the 'TTM Settings' for more information on this issue.</b>");
    die();
}

if(!isset($var)) {
    $var = parse_ini_file('state/var.ini');
}
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
    <title>TTerminal</title>
    <script src="<?=autov('/plugins/dwttm/js/xterm.js');?>"></script>
    <script src="<?=autov('/plugins/dwttm/js/addon-fit.js');?>"></script>
    <link type="text/css" rel="stylesheet" href="<?=autov('/plugins/dwttm/css/xterm.css');?>">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            font-family: Arial, sans-serif;
            background-color: #000;
            overflow: hidden;
        }

        #content {
            display: flex;
            flex-direction: column;
            height: 100%; /* Ensures the content fills the entire viewport */
        }

        #dropdown-container {
            display: flex;
            align-items: center;
            width: 100%;
            background-color: #333; /* Matches dropdown background */
        }

        #session-dropdown {
            flex: 1; /* Ensures dropdown takes up the remaining space */
            height: 40px;
            font-size: 16px;
            border: none;
            outline: none;
            padding-left: 10px;
            padding-right: 10px;
            background-color: #333;
            color: #fff;
        }

        #close-button {
            height: 40px;
            width: 40px;
            background-color: #444;
            color: #888;
            border: none;
            outline: none;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background-color 0.3s ease;
        }

        #close-button:hover {
            background-color: darkred;
            color: white;
        }

        #mouse-button {
            height: 40px;
            width: 40px;
            background-color: #444;
            border: none;
            outline: none;
            cursor: pointer;
            font-size: 18px;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 0;
            transition: background-color 0.3s ease;
        }

        .mouse-on {
            color: white;
        }

        .mouse-off {
            color: #888;
        }

        #terminal-container {
            flex-grow: 1;
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


    <div id="content">
        <?php if (!$currentSession): ?>
            <div id="dropdown-container">
                <select id="session-dropdown"></select>
            </div>
            <div class="new-session-container" id="new-session-container" onclick="createNewSession()">
                <div class="plus-icon">+</div>
                <div class="new-session-text">New Persistent Session</div>
            </div>
        <?php else: ?>
            <div id="dropdown-container">
                <select id="session-dropdown"></select>
                <button id="close-button" onclick="closeSession()">&#x1F5D1;</button>
                <button id="mouse-button" >&#x1F5B1;</button>
            </div>
            <div id="terminal-container"></div>
        <?php endif; ?>
    </div>

    <script>
        const ttimers = {};
        const term = new Terminal({ scrollback: 0 });
        const fitAddon = new FitAddon.FitAddon();
        const dropdown = document.getElementById('session-dropdown');
        let disposable;

        function freeSession() {
        // CHECKED - OK
            window.removeEventListener('resize', handleResize);

            if (ws) {
                ws.onopen = null;
                ws.onmessage = null;
                ws.onerror = null;
                ws.onclose = null;
                ws.close();
                ws = null;
            }

            if (disposable) {
                disposable.dispose();
                disposable = null;
            }
        }

        function fetchSessions() {
        // CHECKED - OK
            fetch('/plugins/dwttm/include/dwttm_sessions.php')
                .then(response => response.json())
                .then(data => {
                    if (data.response) {
                        const sessions = data.response;

                        const currentSession = <?= json_encode($currentSession); ?>;

                        sessions.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                        const dropdown = document.getElementById('session-dropdown');

                        const fragment = document.createDocumentFragment();
                        sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session.session_id;
                            option.textContent = session.session_name;
                            if (session.session_id === currentSession) {
                                option.selected = true;
                                document.title = `${session.session_name}: TTerminal`;
                            }
                            fragment.appendChild(option);
                        });
                        dropdown.innerHTML = '<option value="">Select Session / New Session</option>';
                        dropdown.appendChild(fragment);
                    } else {
                        console.error('Failed to fetch sessions - invalid response.');
                    }
                    if (!data.success && data.error) {
                        console.warn('Error processing sessions:', data.error)
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

        function closeSession() {
        // CHECKED - OK
            const confirmation = confirm("Terminate the session and its running programs?");
            if (!confirmation) {
                return;
            }
            const currentSession = <?= json_encode($currentSession); ?>;
            fetch(`/plugins/dwttm/include/dwttm_close_session.php?session=${encodeURIComponent(currentSession)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        connectToSession();
                    } else {
                        alert(`Failed closing session ${currentSession}: ${response.error}`);
                    }
                })
                .catch(error => {
                    alert(`Failed closing session ${currentSession}: ${error}`);
                });
        }

        function createNewSession() {
        // CHECKED - OK
            fetch('/plugins/dwttm/include/dwttm_new_session.php', {
                method: 'GET',
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    connectToSession(response.session_id);
                } else {
                    alert('Failed to create a new session.');
                }
            })
            .catch(error => console.error('Error creating session:', error));
        }

        function sendTerminalSize() {
        // CHECKED - OK
            fitAddon.fit();
            const { cols, rows } = term;
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'resize',
                    cols,
                    rows,
                }));
            }
        }

        function handleSelectionChange() {
        // CHECKED - OK
            if ("" !== term.getSelection()) {
                try {
                    document.execCommand("copy");
                } catch (error) {
                    console.error("Error copying text:", error);
                    return;
                }
            }

            disposable.dispose();
            term.clearSelection();
            disposable = term.onSelectionChange(handleSelectionChange);
        }

        function handleResize() {
        // CHECKED - OK
            sendTerminalSize();
        }

        function fetchSessionMouse(sessionId) {
        // CHECKED - OK
            const mouseButton = document.getElementById('mouse-button');
            fetch(`/plugins/dwttm/include/dwttm_mouse_session.php?session=${encodeURIComponent(sessionId)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.mouse) {
                        mouseButton.style.display = "flex";
                        mouseButton.onclick = null;

                        if (response.mouse === "on") {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "off");
                            mouseButton.classList.remove("mouse-off");
                            mouseButton.classList.add("mouse-on");
                        } else {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "on");
                            mouseButton.classList.remove("mouse-on");
                            mouseButton.classList.add("mouse-off");
                        }
                    } else {
                        mouseButton.style.display = "none";
                        console.error('Error while fetching mouse for session:', response.error);
                    }
                })
                .catch(error => {
                    mouseButton.style.display = "none";
                    console.error('Error fetching mouse for session:', error);
                });
        }

        function setSessionMouse(sessionId, requestedMouse) {
        // CHECKED - OK
            const mouseButton = document.getElementById('mouse-button');
            fetch(`/plugins/dwttm/include/dwttm_mouse_session.php?session=${encodeURIComponent(sessionId)}&mouse=${encodeURIComponent(requestedMouse)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.newmouse) {
                        mouseButton.onclick = null;

                        if (response.newmouse === "on") {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "off");
                            mouseButton.classList.remove("mouse-off");
                            mouseButton.classList.add("mouse-on");
                        } else {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "on");
                            mouseButton.classList.remove("mouse-on");
                            mouseButton.classList.add("mouse-off");
                        }

                        if (response.requestmouse !== response.newmouse) {
                            alert("Error setting new mouse mode, try again!");
                        }
                    } else {
                        alert('Error setting mouse for session: ' + response.error);
                    }
                })
                .catch(error => {
                    alert('Error setting mouse for session: ' + error);
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
        // CHECKED - OK
            <?php if ($currentSession): ?>
            const terminalContainer = document.getElementById('terminal-container');

            term.loadAddon(fitAddon);
            term.open(terminalContainer);
            fitAddon.fit();

            const currentSession = <?= json_encode($currentSession); ?>;
            const csrfToken = <?= json_encode($var['csrf_token']); ?>;
            const servicePort = <?= json_encode($dwttm_service_port); ?>;

            <?php if ($dwttm_service_route === "direct"): ?>
                const wsUrl = `ws://${window.location.hostname}:${servicePort}/?session=${encodeURIComponent(currentSession)}&csrf=${encodeURIComponent(csrfToken)}`;
            <?php else: ?>
                const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = `${wsProtocol}//${window.location.hostname}/wsproxy/${servicePort}/session/${encodeURIComponent(currentSession)}/csrf/${encodeURIComponent(csrfToken)}`;
            <?php endif; ?>

            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                term.clear();
                sendTerminalSize();
            };

            ws.onmessage = (event) => {
                term.write(event.data);
            };

            ws.onerror = (error) => {
                if (ws.readyState !== WebSocket.OPEN) {
                    term.write('\r\n*** Connection Error: Unable to connect to the requested session. ***\r\n');
                    term.write('\r\nThis is can be caused by using TTM direct routing mode in an SSL/VPN environment.\r\n');
                    term.write('\r\nPlease check your browser console, change the TTM routing mode and/or restart TTM.\r\n');
                }
                console.error('WebSocket error:', error);
            };

            ws.onclose = () => {
                term.write('\r\n*** Disconnected from session ***\r\n');
                freeSession();
            };

            term.onData((data) => {
                if (ws.readyState === WebSocket.OPEN) {
                    ws.send(data);
                }
            });

            disposable = term.onSelectionChange(handleSelectionChange);

            window.addEventListener('resize', handleResize);

            term.focus();
            fetchSessionMouse(currentSession);
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

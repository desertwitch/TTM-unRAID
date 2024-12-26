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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TTerminal</title>
    <script src="<?=autov('/plugins/dwttm/js/xterm.js');?>"></script>
    <script src="<?=autov('/plugins/dwttm/js/addon-fit.js');?>"></script>
    <link type="text/css" rel="stylesheet" href="<?=autov('/plugins/dwttm/css/xterm.css');?>">
    <link type="text/css" rel="stylesheet" href="<?=autov('/plugins/dwttm/css/dwttm-terminal.css');?>">
</head>
<body>


    <div id="dwttm-content">
        <?php if (!$currentSession): ?>
            <div id="dwttm-dropdown-container">
                <select id="dwttm-session-dropdown"></select>
            </div>
            <div class="dwttm-split-container">
                <div class="dwttm-session-half dwttm-top-half" onclick="createNewSession()">
                    <div class="dwttm-plus-icon">+</div>
                    <div class="dwttm-session-text">New Quick Session</div>
                    <div class="dwttm-session-subtext">You can leave and resume your session anytime.</div>
                </div>
                <div class="dwttm-session-half dwttm-bottom-half" onclick="createNewNamedSession()">
                    <div class="dwttm-plus-icon">&#x270E;</div>
                    <div class="dwttm-session-text">New Named Session</div>
                    <div class="dwttm-session-subtext">You can leave and resume your session anytime.</div>
                </div>
            </div>
        <?php else: ?>
            <div id="dwttm-dropdown-container">
                <select id="dwttm-session-dropdown"></select>
                <button id="dwttm-new-button" title="New Session" onclick="<?=($dwttm_plus_button_pop === 'named' ? 'createNewNamedSession()' : 'createNewSession()')?>">+</button>
                <button id="dwttm-rename-button" title="Rename Session" onclick="renameSession()">&#x270E;</button>
                <button id="dwttm-close-button" title="Terminate Session" onclick="closeSession()">&#x1F5D1;</button>
                <button id="dwttm-mouse-button" title="Toggle Scrolling">&#x1F5B1;</button>
            </div>
            <div id="dwttm-terminal-container"></div>
            <div id="dwttm-modal-overlay">
                <div class="dwttm-modal">
                    <button class="dwttm-modal-new" onclick="<?=($dwttm_plus_button_pop === 'named' ? 'createNewNamedSession()' : 'createNewSession()')?>">New Session</button>
                    <button class="dwttm-modal-close" onclick="closeDcModal()">Close Message</button>
                    <h2>Your session has been disconnected.</h2>
                    <p>
                        You can start a new one or close this message to inspect why this happened.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const ttimers = {};
        const term = new Terminal({ scrollback: 0 });
        const fitAddon = new FitAddon.FitAddon();
        const dropdown = document.getElementById('dwttm-session-dropdown');
        const currentSession = <?= json_encode($currentSession ?? ""); ?>;

        let ws;
        let disposable;
        let disposable2;

        function freeSession() {
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

            if (disposable2) {
                disposable2.dispose();
                disposable2 = null;
            }
        }

        function fetchSessions(manuallyInvoked) {
            const dropdown = document.getElementById('dwttm-session-dropdown');
            if(!manuallyInvoked) {
                clearTimeout(ttimers.fetchSessions);
            }
            fetch('/plugins/dwttm/include/dwttm_sessions.php')
                .then(response => response.json())
                .then(data => {
                    if (data.response) {
                        const sessions = data.response;

                        sessions.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                        const fragment = document.createDocumentFragment();
                        sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session.session_id;
                            option.textContent = `${session.session_name} - ${session.created_at}`;
                            if (session.session_id === currentSession) {
                                option.selected = true;
                                document.title = `${session.session_name}: TTerminal`;
                            }
                            fragment.appendChild(option);
                        });
                        dropdown.innerHTML = '<option value="">New Session / Select Session</option>';
                        dropdown.appendChild(fragment);
                    } else {
                        if (data.error) {
                            console.error('Error processing sessions:', data.error)
                        } else {
                            console.error('Error processing sessions, no error message.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching sessions:', error);
                    dropdown.innerHTML = '<option value="">Error loading sessions.</option>';
                })
                .finally(() => {
                    if(!manuallyInvoked) {
                        ttimers.fetchSessions = setTimeout(fetchSessions, 5000);
                    }
                });
        }

        function connectToSession(session) {
            freeSession();
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
            <?php if ($dwttm_close_button !== "noconfirm"): ?>
            const confirmation = confirm("Terminate the session and its running programs?");
            if (!confirmation) {
                return;
            }
            <?php endif; ?>
            fetch(`/plugins/dwttm/include/dwttm_close_session.php?session=${encodeURIComponent(currentSession)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        connectToSession();
                    } else {
                        if(response.error) {
                            alert(`Failed closing session: ${response.error}`);
                            console.error("Error while closing session:", response.error);
                        } else {
                            alert(`Failed closing session, no error message.`);
                            console.error("Error while closing session, no error message.");
                        }
                    }
                })
                .catch(error => {
                    alert(`Failed closing session: ${error}`);
                    console.error("Failed closing session:", error);
                });
        }

        function closeDcModal() {
            document.getElementById('dwttm-modal-overlay').style.display = "none";
        }

        function createNewSession() {
            fetch('/plugins/dwttm/include/dwttm_new_session.php', {
                method: 'GET',
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    connectToSession(response.session_id);
                } else {
                    alert('Failed to create a new session, please try again.');
                    if(response.error) {
                        console.error('Error while creating session:', response.error);
                    } else {
                        console.error('Error while creating session, no error message.');
                    }
                }
            })
            .catch(error => {
                alert('Failed to create session: ' + error);
                console.error('Failed to create session:', error);
            });
        }

        function createNewNamedSession() {
            const sessionName = prompt("Please choose a name for your new session (or leave empty):");

            if (sessionName === null) {
                return;
            } else if (sessionName.trim() !== "") {
                if (!/^[A-Za-z0-9\-]+$/.test(sessionName.trim())) {
                    alert("Invalid session name. Please use alphanumeric characters only.");
                    return;
                }
            }

            const fetchUrl = sessionName.trim() !== ""
            ? `/plugins/dwttm/include/dwttm_new_session.php?session=${encodeURIComponent(sessionName.trim())}`
            : `/plugins/dwttm/include/dwttm_new_session.php`;

            fetch(fetchUrl, {
                method: 'GET',
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    connectToSession(response.session_id);
                } else {
                    alert(
                        'Failed to create a new session' +
                        (sessionName.trim() !== ""
                            ? ', maybe it already exists?'
                            : '.')
                    );
                    if(response.error) {
                        console.error('Error while creating session:', response.error);
                    } else {
                        console.error('Error while creating session, no error message.');
                    }
                }
            })
            .catch(error => {
                alert('Failed to create session: ' + error);
                console.error('Failed to create session:', error);
            });
        }

        function renameSession() {
            const sessionName = prompt("Enter a new session name (alphanumeric only):");

            if(!sessionName) {
                return;
            }

            if (!/^[A-Za-z0-9\-]+$/.test(sessionName.trim())) {
                alert("Invalid session name. Please use alphanumeric characters only.");
                return;
            }

            fetch(`/plugins/dwttm/include/dwttm_rename_session.php?session=${encodeURIComponent(currentSession)}&sessionName=${encodeURIComponent(sessionName.trim())}`, {
                method: 'GET',
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    fetchSessions(true);
                } else {
                    if(response.error) {
                        alert(`Failed to rename session: ${response.error}\nFurther details may be found in the system log, where applicable.`);
                        console.error("Error while renaming session:", response.error);
                    } else {
                        alert(`Failed to rename session, no error message.\nFurther details may be found in the system log, where applicable.`);
                        console.error("Error while renaming session, no error message");
                    }
                }
            })
            .catch(error => {
                alert('Failed to rename session: ' + error);
                console.error('Failed to rename session:', error);
            });
        }

        function sendTerminalSize() {
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
            if ("" !== term.getSelection()) {
                try {
                    document.execCommand("copy");
                } catch (error) {
                    console.error("Error copying text:", error);
                    return;
                }
            }

            if (disposable2) {
                disposable2.dispose();
                disposable2 = null;
            }
            term.clearSelection();
            disposable2 = term.onSelectionChange(handleSelectionChange);
        }

        function handleResize() {
                    sendTerminalSize();
        }

        function fetchSessionMouse(sessionId) {
            const mouseButton = document.getElementById('dwttm-mouse-button');
            fetch(`/plugins/dwttm/include/dwttm_mouse_session.php?session=${encodeURIComponent(sessionId)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.mouse) {
                        mouseButton.style.display = "flex";
                        mouseButton.onclick = null;

                        if (response.mouse === "on") {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "off");
                            mouseButton.classList.remove("dwttm-mouse-off");
                            mouseButton.classList.add("dwttm-mouse-on");
                        } else {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "on");
                            mouseButton.classList.remove("dwttm-mouse-on");
                            mouseButton.classList.add("dwttm-mouse-off");
                        }
                    } else {
                        mouseButton.style.display = "none";
                        if(response.error) {
                            console.error('Error while fetching mouse mode for session:', response.error);
                        } else {
                            console.error('Error while fetching mouse mode for session, no error message.');
                        }
                    }
                })
                .catch(error => {
                    mouseButton.style.display = "none";
                    console.error('Error fetching mouse for session:', error);
                });
        }

        function setSessionMouse(sessionId, requestedMouse) {
            const mouseButton = document.getElementById('dwttm-mouse-button');
            fetch(`/plugins/dwttm/include/dwttm_mouse_session.php?session=${encodeURIComponent(sessionId)}&mouse=${encodeURIComponent(requestedMouse)}`)
                .then(response => response.json())
                .then(response => {
                    if (response.newmouse) {
                        mouseButton.onclick = null;

                        if (response.newmouse === "on") {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "off");
                            mouseButton.classList.remove("dwttm-mouse-off");
                            mouseButton.classList.add("dwttm-mouse-on");
                        } else {
                            mouseButton.onclick = () => setSessionMouse(sessionId, "on");
                            mouseButton.classList.remove("dwttm-mouse-on");
                            mouseButton.classList.add("dwttm-mouse-off");
                        }

                        if(!response.requestmouse || !response.newmouse || response.requestmouse !== response.newmouse) {
                            alert("Error setting new mouse mode, please try again.");
                        }
                    } else {
                        if(response.error) {
                            alert('Error setting mouse mode for session: ' + response.error);
                            console.error('Error setting mouse mode for session:', response.error);
                        } else {
                            alert('Error setting mouse mode for session, no error message.');
                            console.error('Error setting mouse mode for session, no error message.');
                        }
                    }
                })
                .catch(error => {
                    alert('Error setting mouse mode for session: ' + error);
                    console.error('Error setting mouse mode for session:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php if ($currentSession): ?>
            const terminalContainer = document.getElementById('dwttm-terminal-container');

            term.loadAddon(fitAddon);
            term.open(terminalContainer);
            fitAddon.fit();

            const csrfToken = <?= json_encode($var['csrf_token']); ?>;
            const servicePort = <?= json_encode($dwttm_service_port); ?>;

            <?php if ($dwttm_service_route === "direct"): ?>
                const wsUrl = `ws://${window.location.hostname}${window.location.port ? `:${window.location.port}` : ''}:${servicePort}/?session=${encodeURIComponent(currentSession)}&csrf=${encodeURIComponent(csrfToken)}`;
            <?php else: ?>
                const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = `${wsProtocol}//${window.location.hostname}${window.location.port ? `:${window.location.port}` : ''}/wsproxy/${servicePort}/session/${encodeURIComponent(currentSession)}/csrf/${encodeURIComponent(csrfToken)}`;
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
                document.getElementById('dwttm-modal-overlay').style.display = "flex";
                freeSession();
            };

            disposable = term.onData((data) => {
                if (ws.readyState === WebSocket.OPEN) {
                    ws.send(data);
                }
            });
            disposable2 = term.onSelectionChange(handleSelectionChange);

            window.addEventListener('resize', handleResize);

            term.focus();
            fetchSessionMouse(currentSession);
            <?php endif; ?>
            fetchSessions();
        });

        document.addEventListener('change', (event) => {
            if (event.target && event.target.id === 'dwttm-session-dropdown') {
                const selectedSession = event.target.value;
                if(selectedSession !== currentSession) {
                    connectToSession(selectedSession);
                }
            }
        });
    </script>
</body>
</html>

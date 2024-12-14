const term = new Terminal();
let ws; // WebSocket connection
let disposable; // Reference to the onData listener

// Cleanup function to remove existing WebSocket and event listeners
function cleanup() {
    if (ws) {
        ws.close();
        ws = null;
    }

    // Dispose of the previous onData listener
    if (disposable) {
        disposable.dispose();
        disposable = null;
    }
}

function fetchSessions() {
    $.getJSON('/plugins/dwttm/include/dwttm_sessions.php', function(data) {
        if (data.success) {
            const sessions = data.response;
            const $sessionList = $('#session-list');
            $sessionList.empty(); // Clear any existing list items

            $.each(sessions, function(index, sessionName) {
                const $listItem = $('<li>');
                const $link = $('<a>')
                    .text(sessionName)
                    .on('click', function() {
                        connectToSession(sessionName);
                        return false;
                    });
                $listItem.append($link);
                $sessionList.append($listItem);
            });
        } else {
            console.error('Failed to fetch sessions:', data.message);
        }
    }).fail(function(xhr, status, error) {
        console.error('Error fetching sessions:', error);
    });
}

function connectToSession(session) {
    cleanup(); // Clean up before starting a new session

    // Dynamically use the current host and port for WebSocket connection
    const wsUrl = `ws://${window.location.hostname}:3000/ws?session=${session}`;
    ws = new WebSocket(wsUrl);

    ws.onopen = () => {
        console.log(`Connected to TMUX session: ${session}`);
        term.clear();
    };

    ws.onmessage = (event) => {
        term.write(event.data); // Write data to the terminal
    };

    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    ws.onclose = () => {
        console.log(`Disconnected from TMUX session: ${session}`);
        term.write('\r\n*** Disconnected from session ***\r\n');
    };

    // Add a new onData listener and store its reference
    disposable = term.onData(data => {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(data); // Send input from xterm to the WebSocket
        }
    });
}

package main

import (
	"bufio"
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/exec"
	"os/signal"
	"regexp"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/creack/pty"
	"github.com/gorilla/mux"
	"github.com/gorilla/websocket"
)

var internalServer bool

var (
	csrfToken     string
	csrfChecking  bool
	csrfTokenLock sync.RWMutex
)

var upgrader = websocket.Upgrader{
	CheckOrigin: func(r *http.Request) bool { return true },
}

type activeShell struct {
	cleanedState  bool
	sessionName   string
	sessionCancel context.CancelFunc
	cmd           *exec.Cmd
	ptmx          *os.File
	conn          *websocket.Conn
	mu            sync.Mutex
}

var activeShells = sync.Map{}

func retry(attempts int, fn func() error) error {
	var err error
	for i := 0; i < attempts; i++ {
		err = fn()
		if err == nil {
			return nil
		}
		log.Printf("Attempt %d failed: %v\n", i+1, err)
	}
	return err
}

func sanitizeInputString(session string) string {
	re := regexp.MustCompile(`[^a-zA-Z0-9_\-$]`)
	return re.ReplaceAllString(session, "")
}

func checkTmuxSessionExists(session string) bool {
	cmd := exec.Command("tmux", "has-session", "-t", session)
	return cmd.Run() == nil
}

func readCSRFToken(ctx context.Context, mainWG *sync.WaitGroup) {
	defer func() {
		log.Printf("CSRF handling goroutine exited.\n")
		mainWG.Done()
	}()

	filePath := "/var/local/emhttp/var.ini"

	for {
		select {
		case <-ctx.Done():
			return
		default:
			file, err := os.Open(filePath)
			if err != nil {
				log.Printf("Error opening file %s: %v", filePath, err)
				time.Sleep(5 * time.Second)
				continue
			}

			scanner := bufio.NewScanner(file)
			var token string
			for scanner.Scan() {
				line := scanner.Text()
				if strings.HasPrefix(line, "csrf_token=") {
					token = strings.TrimPrefix(line, "csrf_token=")
					if strings.HasPrefix(token, "\"") && strings.HasSuffix(token, "\"") {
						token = strings.Trim(token, "\"")
					}
					break
				}
			}
			file.Close()

			if err := scanner.Err(); err != nil {
				log.Printf("Error reading file %s: %v", filePath, err)
			}

			if token != "" {
				csrfTokenLock.Lock()
				if csrfToken != token {
					csrfToken = token
					log.Printf("Read and stored the system's CSRF token.\n")
				}
				csrfTokenLock.Unlock()
			} else {
				log.Printf("CSRF token not found in file: %s", filePath)
			}

			time.Sleep(10 * time.Second)
		}
	}
}

func outputActiveShells(ctx context.Context, mainWG *sync.WaitGroup) {
	defer func() {
		log.Printf("Shell reporting goroutine exited.\n")
		mainWG.Done()
	}()

	ticker := time.NewTicker(3 * time.Hour)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			count := 0
			activeShells.Range(func(_, _ interface{}) bool {
				count++
				return true
			})
			log.Printf("Attached TMUX shells: %d\n", count)
		}
	}
}

func shellWebSocketHandler(w http.ResponseWriter, r *http.Request, mainWG *sync.WaitGroup) {
	defer func() {
		log.Printf("WebSocket handler goroutine exited.\n")
		mainWG.Done()
	}()

	var cleanupStarted bool
	sessionWG := &sync.WaitGroup{}

	vars := mux.Vars(r)

	log.Printf("WebSocket connection initiated.\n")

	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("WebSocket upgrade failed: %v\n", err)
		return
	}
	defer func() {
		if !cleanupStarted {
			conn.Close()
		}
	}()

	conn.SetCloseHandler(func(code int, text string) error {
		log.Printf("WebSocket closed by client. Code: %d, Text: %s\n", code, text)
		return nil
	})

	session := vars["session"]
	if session == "" {
		session = r.URL.Query().Get("session")
	}
	session = sanitizeInputString(session)
	if session == "" {
		log.Printf("Invalid session name received.\n")
		conn.WriteMessage(websocket.TextMessage, []byte("Invalid session name"))
		return
	}
	log.Printf("%s -- Session request received.\n", session)

	if csrfChecking {
		csrfTokenLock.Lock()
		currentToken := csrfToken
		csrfTokenLock.Unlock()

		authtoken := vars["csrf"]
		if authtoken == "" {
			authtoken = r.URL.Query().Get("csrf")
		}
		authtoken = sanitizeInputString(authtoken)
		if authtoken == "" || authtoken != currentToken {
			log.Printf("%s -- Invalid CSRF token received: %s\n", session, authtoken)
			conn.WriteMessage(websocket.TextMessage, []byte("Invalid CSRF token, reload the page."))
			return
		}
		log.Printf("%s -- CSRF authenticated.\n", session)
	}

	if !checkTmuxSessionExists(session) {
		log.Printf("%s -- TMUX session does not exist.\n", session)
		conn.WriteMessage(websocket.TextMessage, []byte("Session does not exist"))
		return
	}

	cmd := exec.Command("tmux", "new-session", "-A", "-t", session, "-x", "80", "-y", "24")
	cmd.Env = append(cmd.Env, "TERM=xterm-256color")

	ptmx, err := pty.Start(cmd)
	if err != nil {
		log.Printf("%s -- Failed to start PTY for TMUX session: %v\n", session, err)
		conn.WriteMessage(websocket.TextMessage, []byte("Failed to start PTY for TMUX session"))
		return
	}
	defer func() {
		if !cleanupStarted {
			ptmx.Close()
			cmd.Process.Kill()
		}
	}()
	log.Printf("%s -- TMUX session started successfully with PTY.\n", session)

	ctx, cancel := context.WithCancel(context.Background())
	defer func() {
		if !cleanupStarted {
			cancel()
		} else {
			sessionWG.Wait()
		}
	}()

	activeShells.Store(conn, &activeShell{
		sessionName:   session,
		sessionCancel: cancel,
		cmd:           cmd,
		ptmx:          ptmx,
		conn:          conn,
	})

	defer func() {
		cleanupStarted = true
		sessionWG.Add(1)
		cleanupConn(conn, sessionWG)
	}()
	log.Printf("%s -- TMUX session stored in active shells register.\n", session)

	sessionWG.Add(1)
	go func() {
		defer func() {
			log.Printf("%s -- Ping-pong goroutine exited.\n", session)
			sessionWG.Done()
		}()

		ticker := time.NewTicker(30 * time.Second)
		defer ticker.Stop()

		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				if err := conn.WriteControl(websocket.PingMessage, nil, time.Now().Add(10*time.Second)); err != nil {
					log.Printf("%s -- Ping failed: %v\n", session, err)
					cancel()
					return
				}
			}
		}
	}()

	sessionWG.Add(1)
	go func() {
		defer func() {
			log.Printf("%s -- Reader goroutine exited.\n", session)
			sessionWG.Done()
		}()
		buf := make([]byte, 1024)
		for {
			select {
			case <-ctx.Done():
				return
			default:
				n, err := ptmx.Read(buf)
				if err != nil {
					log.Printf("%s -- PTY read error: %v\n", session, err)
					conn.WriteMessage(websocket.TextMessage, []byte("Session ended."))
					cancel()
					return
				}
				conn.SetWriteDeadline(time.Now().Add(30 * time.Second))
				if err := conn.WriteMessage(websocket.TextMessage, buf[:n]); err != nil {
					log.Printf("%s -- WebSocket write error: %v\n", session, err)
					cancel()
					return
				}
			}
		}
	}()

	sessionWG.Add(1)
	go func() {
		defer func() {
			log.Printf("%s -- Writer goroutine exited.\n", session)
			sessionWG.Done()
		}()
		for {
			select {
			case <-ctx.Done():
				return
			default:
				_, message, err := conn.ReadMessage()
				if err != nil {
					log.Printf("%s -- WebSocket read error: %v\n", session, err)
					cancel()
					return
				}

				var resizeMessage struct {
					Type string `json:"type"`
					Cols int    `json:"cols"`
					Rows int    `json:"rows"`
				}
				if err := json.Unmarshal(message, &resizeMessage); err == nil && resizeMessage.Type == "resize" {
					if err := pty.Setsize(ptmx, &pty.Winsize{
						Cols: uint16(resizeMessage.Cols),
						Rows: uint16(resizeMessage.Rows),
					}); err != nil {
						log.Printf("%s -- Failed to resize PTY: %v\n", session, err)
					}
					continue
				}

				if _, err := ptmx.Write(message); err != nil {
					log.Printf("%s -- PTY write error: %v\n", session, err)
					cancel()
					return
				}
			}
		}
	}()

	<-ctx.Done()
}

func cleanupConn(conn *websocket.Conn, sessionWG *sync.WaitGroup) {
	defer func() {
		log.Printf("Garbage collection goroutine exited.\n")
		sessionWG.Done()
	}()
	if shell, loaded := activeShells.LoadAndDelete(conn); loaded {
		if s, ok := shell.(*activeShell); ok {
			s.mu.Lock()
			defer s.mu.Unlock()

			sessionName := s.sessionName

			if s.cleanedState {
				log.Printf("%s -- Already cleaned - not cleaning again.\n", sessionName)
				return
			}

			if s.sessionCancel != nil {
				if err := retry(2, func() error {
					s.sessionCancel()
					return nil
				}); err != nil {
					log.Printf("%s -- Failed to signal goroutine cancellation: %v\n", s.sessionName, err)
				} else {
					log.Printf("%s -- Goroutine cancellation signalled.\n", s.sessionName)
				}
			}

			if s.ptmx != nil {
				if err := retry(2, func() error { return s.ptmx.Close() }); err != nil {
					log.Printf("%s -- Failed to close PTY after retrying: %v\n", sessionName, err)
				}
			}

			if s.cmd.Process != nil {
				if err := s.cmd.Process.Signal(syscall.SIGTERM); err != nil {
					log.Printf("%s -- Failed to terminate TMUX process: %v\n", sessionName, err)
				}

				waitDone := make(chan struct{})
				go func() {
					s.cmd.Wait()
					close(waitDone)
				}()

				select {
				case <-waitDone:
				case <-time.After(10 * time.Second):
					if killErr := s.cmd.Process.Kill(); killErr != nil {
						log.Printf("%s -- Failed to kill TMUX process: %v\n", sessionName, killErr)
					}
				}
			}

			if conn != nil {
				if err := retry(2, func() error { return conn.Close() }); err != nil {
					log.Printf("%s -- Failed to close WebSocket after retrying: %v\n", sessionName, err)
				} else {
					log.Printf("%s -- Websocket gracefully closed.\n", sessionName)
				}
			}

			log.Printf("%s -- Session is now all cleaned up.\n", sessionName)
			s.cleanedState = true
		}
	}
}

func main() {
	port := flag.String("port", "49161", "Port to run the server on")
	flag.BoolVar(&csrfChecking, "csrf", true, "Enable CSRF authentication mechanism")
	flag.BoolVar(&internalServer, "internal", false, "Allow only connections from 127.0.0.1")

	flag.Parse()

	var addr string
	if internalServer {
		addr = fmt.Sprintf("127.0.0.1:%s", *port)
		log.Printf("Internal mode - port closed to external connections.\n")
	} else {
		addr = fmt.Sprintf(":%s", *port)
	}

	r := mux.NewRouter()

	mainWG := &sync.WaitGroup{}

	r.HandleFunc("/session/{session}/csrf/{csrf}", func(w http.ResponseWriter, r *http.Request) {
		mainWG.Add(1)
		shellWebSocketHandler(w, r, mainWG)
	})

	r.HandleFunc("/session/{session}", func(w http.ResponseWriter, r *http.Request) {
		mainWG.Add(1)
		shellWebSocketHandler(w, r, mainWG)
	})

	r.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		mainWG.Add(1)
		shellWebSocketHandler(w, r, mainWG)
	})

	listener, err := net.Listen("tcp", addr)
	if err != nil {
		log.Fatalf("Failed to start server on %s: %v\n", addr, err)
	}
	log.Printf("Server starting on %s\n", addr)

	srv := &http.Server{Addr: addr, Handler: r}

	go func() {
		if err := srv.Serve(listener); err != nil && err != http.ErrServerClosed {
			log.Fatalf("HTTP server error: %v\n", err)
		}
	}()

	ctxHelpers, cancelHelpers := context.WithCancel(context.Background())
	defer cancelHelpers()

	if csrfChecking {
		log.Printf("CSRF authentication mechanism enabled.\n")
		mainWG.Add(1)
		go readCSRFToken(ctxHelpers, mainWG)
	} else {
		log.Printf("CSRF authentication mechanism disabled.\n")
	}

	mainWG.Add(1)
	go outputActiveShells(ctxHelpers, mainWG)

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, os.Interrupt, syscall.SIGTERM)
	<-quit

	log.Printf("Shutting down server...\n")

	cancelHelpers()

	activeShells.Range(func(key, value interface{}) bool {
		if s, ok := value.(*activeShell); ok {
			s.mu.Lock()
			if s.sessionCancel != nil {
				s.sessionCancel()
			}
			s.mu.Unlock()
		}
		return true
	})

	waitDone := make(chan struct{})
	go func() {
		mainWG.Wait()
		close(waitDone)
	}()

	select {
	case <-waitDone:
		log.Println("All cleanup tasks completed.")
	case <-time.After(40 * time.Second):
		log.Println("Timeout reached while waiting for cleanup tasks.")
	}

	ctxSrv, cancelSrv := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancelSrv()
	if err := srv.Shutdown(ctxSrv); err != nil {
		log.Printf("Server shutdown failed: %v", err)
	} else {
		log.Printf("Server shutdown was completed.\n")
	}

	log.Printf("Program exiting - bye for now.\n")
}

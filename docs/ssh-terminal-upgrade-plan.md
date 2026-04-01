# SSH Terminal Upgrade Plan

## Goal
Replace the current command-runner terminal with a true SSH terminal that behaves more like PuTTY or `ssh` in a real shell.

## Current Model
- Browser-based xterm.js UI
- One command at a time
- Commands are queued and executed separately
- Output is replayed back into the UI
- No persistent shell state

## Target Model
- Persistent SSH session per terminal window
- Allocated PTY
- Live bidirectional stream over websockets
- User keystrokes forwarded immediately to the remote shell
- Shell state preserved for the session lifetime

## Recommended Phased Path

### Phase 1: Session Foundation
- Add `terminal_sessions` storage
- Track:
  - server/site association
  - session status
  - user
  - SSH host and port
  - PTY size
  - timestamps
- Keep a separate event/log stream for audit history

### Phase 2: Websocket Transport
- Introduce a websocket channel for terminal input/output
- Keep the browser terminal open for the duration of the session
- Support resize, reconnect, and close events

### Phase 3: SSH/PTTY Bridge
- Add a persistent SSH bridge process
- Open SSH with a PTY
- Forward stdin/stdout/stderr over the websocket
- Maintain current working directory and environment state

### Phase 4: Recovery and Safety
- Add reconnect grace windows
- Add heartbeat/idle timeout handling
- Record session start/end and errors
- Enforce authorization and audit logs

### Phase 5: Cutover
- Use the new PTY terminal for interactive work
- Keep the current command-runner for background jobs and automation
- Retire the command-runner from interactive use once the PTY shell is stable

## Notes
- Some shared hosts and cPanel environments may still restrict true PTY sessions.
- If SSH is limited by the host, the app should degrade gracefully and explain the restriction.
- The current command-runner terminal remains useful for non-interactive tasks, deployment steps, and automation.

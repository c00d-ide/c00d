package db

import (
	"time"
)

// CreateSession stores a new session in the database
func CreateSession(sessionID string, duration time.Duration) error {
	expiresAt := time.Now().Add(duration)
	_, err := DB.Exec(
		"INSERT OR REPLACE INTO sessions (id, expires_at, last_accessed) VALUES (?, ?, ?)",
		sessionID, expiresAt, time.Now(),
	)
	return err
}

// ValidateSession checks if a session exists and is not expired
func ValidateSession(sessionID string) bool {
	var expiresAt time.Time
	err := DB.QueryRow("SELECT expires_at FROM sessions WHERE id = ?", sessionID).Scan(&expiresAt)
	if err != nil {
		return false
	}
	if time.Now().After(expiresAt) {
		DeleteSession(sessionID)
		return false
	}
	// Update last accessed time
	DB.Exec("UPDATE sessions SET last_accessed = ? WHERE id = ?", time.Now(), sessionID)
	return true
}

// DeleteSession removes a session from the database
func DeleteSession(sessionID string) {
	DB.Exec("DELETE FROM sessions WHERE id = ?", sessionID)
}

// CleanupExpiredSessions removes all expired sessions
func CleanupExpiredSessions() {
	DB.Exec("DELETE FROM sessions WHERE expires_at < ?", time.Now())
}

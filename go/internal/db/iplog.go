package db

import (
	"net"
	"net/http"
	"strings"
)

// LogIP logs an IP address with request details
func LogIP(r *http.Request) {
	ip := GetClientIP(r)
	DB.Exec(
		"INSERT INTO ip_logs (ip_address, endpoint, method, user_agent) VALUES (?, ?, ?, ?)",
		ip, r.URL.Path, r.Method, r.UserAgent(),
	)
}

// GetClientIP extracts the client IP from the request
func GetClientIP(r *http.Request) string {
	// Check X-Forwarded-For header (for proxies/load balancers)
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		// Take the first IP in the list
		if idx := strings.Index(xff, ","); idx != -1 {
			return strings.TrimSpace(xff[:idx])
		}
		return strings.TrimSpace(xff)
	}

	// Check X-Real-IP header
	if xri := r.Header.Get("X-Real-IP"); xri != "" {
		return strings.TrimSpace(xri)
	}

	// Fall back to RemoteAddr
	ip, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return ip
}

// IPLogEntry represents a logged IP entry
type IPLogEntry struct {
	ID        int64  `json:"id"`
	IPAddress string `json:"ip_address"`
	Endpoint  string `json:"endpoint"`
	Method    string `json:"method"`
	UserAgent string `json:"user_agent"`
	CreatedAt string `json:"created_at"`
}

// GetIPLogs retrieves recent IP logs
func GetIPLogs(limit int) ([]IPLogEntry, error) {
	if limit <= 0 {
		limit = 100
	}

	rows, err := DB.Query(`
		SELECT id, ip_address, endpoint, method, user_agent, created_at
		FROM ip_logs
		ORDER BY created_at DESC
		LIMIT ?
	`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var logs []IPLogEntry
	for rows.Next() {
		var entry IPLogEntry
		if err := rows.Scan(&entry.ID, &entry.IPAddress, &entry.Endpoint, &entry.Method, &entry.UserAgent, &entry.CreatedAt); err != nil {
			continue
		}
		logs = append(logs, entry)
	}
	return logs, nil
}

// GetUniqueIPs returns unique IPs with their last seen time and request count
func GetUniqueIPs(limit int) ([]map[string]any, error) {
	if limit <= 0 {
		limit = 100
	}

	rows, err := DB.Query(`
		SELECT ip_address, COUNT(*) as request_count, MAX(created_at) as last_seen
		FROM ip_logs
		GROUP BY ip_address
		ORDER BY last_seen DESC
		LIMIT ?
	`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results []map[string]any
	for rows.Next() {
		var ip string
		var count int
		var lastSeen string
		if err := rows.Scan(&ip, &count, &lastSeen); err != nil {
			continue
		}
		results = append(results, map[string]any{
			"ip_address":    ip,
			"request_count": count,
			"last_seen":     lastSeen,
		})
	}
	return results, nil
}

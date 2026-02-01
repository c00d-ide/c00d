package handlers

import (
	"embed"
	"io/fs"
	"net/http"
	"strings"
)

// FrontendFS holds the embedded frontend files
var FrontendFS embed.FS

// Frontend serves the embedded frontend files
func Frontend(w http.ResponseWriter, r *http.Request) {
	path := r.URL.Path
	if path == "/" {
		path = "/index.html"
	}

	// Try to serve from embedded filesystem
	content, err := fs.ReadFile(FrontendFS, "frontend"+path)
	if err != nil {
		// Serve index.html for SPA routing
		content, _ = fs.ReadFile(FrontendFS, "frontend/index.html")
		path = "/index.html"
	}

	// Set content type
	switch {
	case strings.HasSuffix(path, ".html"):
		w.Header().Set("Content-Type", "text/html")
	case strings.HasSuffix(path, ".css"):
		w.Header().Set("Content-Type", "text/css")
	case strings.HasSuffix(path, ".js"):
		w.Header().Set("Content-Type", "application/javascript")
	case strings.HasSuffix(path, ".json"):
		w.Header().Set("Content-Type", "application/json")
	}

	w.Write(content)
}

package auth

import (
	"net/http"

	"github.com/c00d-ide/c00d/internal/config"
	"github.com/c00d-ide/c00d/internal/db"
)

// Middleware checks for valid session authentication
func Middleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if config.C.Password == "" {
			next(w, r)
			return
		}

		cookie, err := r.Cookie("c00d_session")
		if err != nil {
			http.Error(w, `{"error":"unauthorized"}`, http.StatusUnauthorized)
			return
		}

		if !db.ValidateSession(cookie.Value) {
			http.Error(w, `{"error":"unauthorized"}`, http.StatusUnauthorized)
			return
		}

		next(w, r)
	}
}

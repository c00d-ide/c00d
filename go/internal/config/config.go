package config

import (
	"os"
	"path/filepath"

	"gopkg.in/yaml.v3"
)

// Config holds all configuration
type Config struct {
	Port     int    `yaml:"port"`
	BasePath string `yaml:"base_path"`
	Password string `yaml:"password"`
	DataDir  string `yaml:"data_dir"`

	AI struct {
		Provider   string `yaml:"provider"` // c00d, anthropic, openai, ollama
		LicenseKey string `yaml:"license_key"`
		APIKey     string `yaml:"api_key"`
		Model      string `yaml:"model"`
		OllamaURL  string `yaml:"ollama_url"`
	} `yaml:"ai"`

	Editor struct {
		Theme    string `yaml:"theme"`
		FontSize int    `yaml:"font_size"`
		TabSize  int    `yaml:"tab_size"`
	} `yaml:"editor"`

	Security struct {
		AllowedIPs   []string `yaml:"allowed_ips"`
		RequireHTTPS bool     `yaml:"require_https"`
		LogIPs       *bool    `yaml:"log_ips"` // Pointer to distinguish unset from false
	} `yaml:"security"`
}

// Global config instance
var C Config

// Load reads config from file
func Load(path string) {
	data, err := os.ReadFile(path)
	if err != nil {
		// No config file, use defaults
		return
	}
	yaml.Unmarshal(data, &C)
}

// ApplyDefaults sets default values for unset config options
func ApplyDefaults() {
	if C.Port == 0 {
		C.Port = 3000
	}
	if C.BasePath == "" {
		C.BasePath, _ = os.Getwd()
	}
	if C.DataDir == "" {
		C.DataDir = filepath.Join(C.BasePath, ".c00d")
	}
	if C.AI.Model == "" {
		C.AI.Model = "claude-sonnet-4-20250514"
	}
	if C.AI.OllamaURL == "" {
		C.AI.OllamaURL = "http://localhost:11434"
	}
	if C.Editor.FontSize == 0 {
		C.Editor.FontSize = 14
	}
	if C.Editor.TabSize == 0 {
		C.Editor.TabSize = 4
	}
	if C.Editor.Theme == "" {
		C.Editor.Theme = "vs-dark"
	}
	// Default LogIPs to true if not set
	if C.Security.LogIPs == nil {
		defaultTrue := true
		C.Security.LogIPs = &defaultTrue
	}
}

// ShouldLogIPs returns whether IP logging is enabled
func ShouldLogIPs() bool {
	if C.Security.LogIPs == nil {
		return true
	}
	return *C.Security.LogIPs
}

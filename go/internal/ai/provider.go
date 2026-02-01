package ai

// Provider defines the interface for AI providers
type Provider interface {
	Chat(system string, messages []map[string]string) map[string]any
}

.PHONY: build build-all clean run

# Build for current platform
build:
	go build -ldflags="-s -w" -o c00d

# Build for all platforms
build-all: clean
	@mkdir -p dist
	GOOS=linux GOARCH=amd64 go build -ldflags="-s -w" -o dist/c00d-linux-amd64
	GOOS=linux GOARCH=arm64 go build -ldflags="-s -w" -o dist/c00d-linux-arm64
	GOOS=darwin GOARCH=amd64 go build -ldflags="-s -w" -o dist/c00d-darwin-amd64
	GOOS=darwin GOARCH=arm64 go build -ldflags="-s -w" -o dist/c00d-darwin-arm64
	GOOS=windows GOARCH=amd64 go build -ldflags="-s -w" -o dist/c00d-windows-amd64.exe
	@echo "Built all binaries in dist/"
	@ls -lh dist/

# Clean build artifacts
clean:
	rm -f c00d
	rm -rf dist/

# Run development server
run:
	go run .

# Install dependencies
deps:
	go mod download
	go mod tidy

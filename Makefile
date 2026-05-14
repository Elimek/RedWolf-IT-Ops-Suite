# Makefile for RedWolf IT Officer Demo
# ================================

.PHONY: test test-unit test-integration test-e2e test-perf test-chunk1 test-chunk2 test-chunk3 test-chunk4 clean help

# Default target
help:
	@echo "RedWolf IT Officer Demo - Makefile"
	@echo "==================================="
	@echo ""
	@echo "Targets:"
	@echo "  test          Run all tests"
	@echo "  test-unit     Run unit tests only"
	@echo "  test-integration  Run integration tests"
	@echo "  test-e2e      Run end-to-end tests"
	@echo "  test-perf     Run performance tests"
	@echo "  test-chunkN   Run specific chunk test (N=1-4)"
	@echo "  clean         Clean test reports and logs"
	@echo "  help          Show this help"
	@echo ""

# Run all tests
test:
	@echo "Running all test suites..."
	@bash tests/run_all.sh all

# Unit tests only
test-unit:
	@echo "Running unit tests..."
	@bash tests/run_all.sh unit

# Integration tests
test-integration:
	@echo "Running integration tests..."
	@bash tests/run_all.sh integration

# E2E tests
test-e2e:
	@echo "Running E2E tests..."
	@bash tests/run_all.sh e2e

# Performance tests
test-perf:
	@echo "Running performance tests..."
	@bash tests/run_all.sh performance

# Chunk-specific tests
test-chunk1:
	@bash tests/run_all.sh chunk1

test-chunk2:
	@bash tests/run_all.sh chunk2

test-chunk3:
	@bash tests/run_all.sh chunk3

test-chunk4:
	@bash tests/run_all.sh chunk4

# Clean
clean:
	@echo "Cleaning test reports and logs..."
	rm -f tests/reports/*.xml tests/reports/*.html
	@echo "Done."

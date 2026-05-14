# Automated Test Suite

Enterprise-grade test harness covering all RedWolf IT Ops modules.

## Structure
```
tests/
├── run_all.sh             # Master test runner
├── Makefile               # make test / make test-unit
├── unit/                  # Static analysis & unit tests
├── integration/           # API integration tests
├── e2e/                   # End-to-end browser tests
├── performance/           # Load & stress tests
├── fixtures/              # Test data & mock servers
└── reports/               # JUnit XML & HTML reports
```

## Run Tests
```bash
# All tests
bash tests/run_all.sh all

# Specific suite
bash tests/run_all.sh unit
bash tests/run_all.sh integration
bash tests/run_all.sh e2e
bash tests/run_all.sh performance

# Via Makefile
make test
make test-unit
```

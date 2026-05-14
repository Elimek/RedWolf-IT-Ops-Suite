请创建企业级测试 Harness，覆盖 Chunk 1-4 所有功能。

# 角色
你是 **QA 工程师**，专长自动化测试流水线。

# 目录结构
```
tests/
├── README.md
├── run_all.sh
├── Makefile
├── unit/
│   ├── shellcheck.sh
│   ├── php_lint.sh
│   └── security_scan.sh
├── integration/
│   └── api/...
├── e2e/
│   └── selenium/...
├── performance/
├── fixtures/
└── reports/
```

# 核心脚本

## 5.1 `tests/run_all.sh`
支持 all|unit|integration|e2e|performance 参数，统计 passed/total。

## 5.2 单元测试
shellcheck、php -l、安全扫描。

## 5.3 集成测试 `tests/integration/api/test_product_api.py`
pytest + requests，测试产品页、库存更新、并发安全、CSRF 保护。

## 5.4 CI/CD `.github/workflows/ci.yml`
GitHub Actions 配置，包含 MySQL 服务、多级测试、缓存。

## 5.5 `Makefile`
test/test-unit/test-e2e/test-perf 目标。

# 约束
- 测试幂等
- 失败输出清晰修复建议
- CI 生成 JUnit XML
- Docker 隔离环境

请生成所有测试文件。

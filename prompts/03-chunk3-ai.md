请实现 Chunk 3 - 本地 AI 工单智能分类系统（隐私优先）。

# 角色
你是 **MLOps 工程师**，专长落地大模型到企业 IT 运维。坚持：数据不出内网、零 API 成本、准确率 >85%、响应 <3s。

# 架构
前端 (classifier.html) → PHP API (api_endpoint.php) → Python FastAPI → Ollama (qwen2.5:7b)

# 核心文件

## 3.1 Prompt 模板 `ai_agent/prompt_template.txt`
你是 RedWolf Airsoft 的 IT 支持专家，分类工单。输出纯 JSON。

## 3.2 FastAPI 核心 `ai_agent/core/classifier.py`
实现 /classify 端点，调用 Ollama，超时 5 秒，fallback 到关键词匹配。

## 3.3 PHP 桥接 `ai_agent/api_endpoint.php`
接收 POST，curl 调用 Python 服务，缓存结果。

## 3.4 前端 `ai_agent/classifier.html`
文本框、分类按钮、结果卡片、历史记录、示例工单。

## 3.5 数据 `ai_agent/data/test.json`
50 个标注好的工单样本。

## 3.6 准确率测试 `ai_agent/test_accuracy.py`
计算准确率、精确率、召回率，输出报告。

## 3.7 部署脚本 `deployment/chunk3-ai.sh`
启动 Ollama、下载模型、启动 FastAPI、验证。

## 3.8 测试脚本 `tests/chunk3/test_suite.sh`
5 项测试：Ollama 服务、FastAPI 健康、分类 API、准确率、性能。

## 3.9 Windows 部署 `docs/windows-ai-deployment.md`

## 3.10 隐私文档 `docs/ai-privacy.md`

# 约束
- Python 类型提示
- fallback 机制
- Prompt 单独文件
- 性能基准记录

请生成所有文件。

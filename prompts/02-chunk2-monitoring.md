请实现 Chunk 2 - 服务器监控与告警系统。

# 角色
你是 **Linux 系统管理员，10 年运维经验**，服务过大型电商网站 24/7 运行。

# 功能需求

## 2.1 数据采集器 `monitoring/collector.sh`
- 每 60 秒执行（通过 cron）
- 采集指标：CPU idle、内存使用率、磁盘使用、网络流量、Top 10 进程
- 输出 JSON 到 `/var/log/redwolf/metrics/$(date +%Y-%m-%d).jsonl`
- 日志轮转：保留 7 天

## 2.2 监控面板 `monitoring/dashboard.php`
- 顶部：服务器时间 + 运行天数
- 4 个 Bootstrap 卡片：CPU（圆形进度条）、内存、磁盘、网络（Chart.js 实时曲线）
- 底部表格：Top 10 进程
- 自动刷新：每 60 秒

## 2.3 告警管理器 `monitoring/alert_manager.php`
- 检查条件：CPU>90%持续3次、内存>85%、磁盘>90%、Nginx 5xx>5%
- 动作：写入数据库、发送邮件、可选 Webhook
- 防骚扰：1 小时内相同告警不重复

## 2.4 故障模拟器 `monitoring/fault_simulator.php`
- 仅 admin 可访问
- 按钮：模拟高 CPU、内存泄漏、磁盘写满、停止 Nginx、恢复所有
- 记录审计日志
- 实时显示系统状态图表

## 2.5 部署脚本 `deployment/chunk2-monitoring.sh`
安装依赖、配置 cron、验证 dashboard.php 可访问。

## 2.6 测试脚本 `tests/chunk2/test_suite.sh`
5 项测试：collector 生成 JSON、dashboard 显示数据、告警触发、故障模拟器、cron 任务。

## 2.7 文档 `docs/chunk2-monitoring-guide.md`
监控指标说明、告警处理 SOP、自定义阈值、性能影响。

# 约束
- Shell: `set -euo pipefail`
- PHP: 类型声明（PHP 8.2）
- UI 文本：繁体中文
- Chart.js: CDN

请生成所有文件。

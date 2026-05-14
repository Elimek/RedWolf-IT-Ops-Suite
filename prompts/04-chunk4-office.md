请实现 Chunk 4 - IT 办公支持工具包。

# 角色
你是 **企业 IT 支持工程师**，服务过 500 人规模公司。工具必须简单、安全、直观。

# 工具清单

## 4.1 网络扫描器 `office_tools/network_scanner.php`
- 输入：起始 IP、结束 IP（默认 192.168.1.1-254）
- 功能：ping 扫描 + 端口扫描(22/80/443/3389/5900) + 反向 DNS
- 输出：HTML 表格 + CSV 导出
- 安全：只允许私有网段，需要 admin 登录
- 性能：/24 网段 < 30 秒

## 4.2 打印机配置助手 `office_tools/printer_config.html`
4 个工具：IP 计算器、端口检测、错误代码查询、驱动下载。

## 4.3 VPN 状态检查 `office_tools/vpn_status.php`
检测 OpenVPN/WireGuard，显示状态、虚拟 IP、流量、一键重连。

## 4.4 部署脚本 `deployment/chunk4-office.sh`
配置 sudoers、设置权限、创建日志目录。

## 4.5 Windows 版本 `windows_tools/`
PowerShell 脚本实现相同功能。

## 4.6 测试脚本 `tests/chunk4/test_suite.sh`
4 项功能测试。

## 4.7 用户文档 `docs/office-tools-user-guide.md`
针对非技术人员，配图说明。

# 约束
- 输入验证
- 命令注入防护
- UI: Bootstrap 5
- 危险操作记录审计日志

请生成所有文件。

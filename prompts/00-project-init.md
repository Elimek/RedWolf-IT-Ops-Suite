# 阶段 0: 项目初始化

请创建 RedWolf-IT-Ops-Suite 项目，严格按以下规格：

# 项目信息
- 名称: RedWolf IT Officer Demo
- 目标岗位: Assistant IT Officer / IT Officer (RedWolf Airsoft Specialist Ltd)
- 技术栈: **PHP 8.2 + MySQL 8.0 + Linux (Ubuntu 22.04)** — 必须严格匹配 JD 要求
- 架构原则: 零依赖(不引入 Composer/NPM)、生产就绪、分块部署、隐私优先

# 生成内容
请一次性创建以下文件结构：

```
RedWolf-IT-Ops-Suite/
├── docker-compose.yml          # 完整编排(web+db+phpmyadmin+ollama)
├── .env.example                # 所有环境变量说明
├── .gitignore                  # 排除数据/日志
├── README.md                   # 项目主页(我会后续填充)
├── ARCHITECTURE.md             # 技术决策记录
├── SPEC.md                     # 本规格说明书
├── deployment/                 # 分块部署脚本
│   ├── chunk1-core.sh
│   ├── chunk2-monitoring.sh
│   ├── chunk3-ai.sh
│   └── chunk4-office.sh
├── magento_lite/               # Chunk 1: Magento Lite 平台
├── monitoring/                 # Chunk 2: 服务器监控
├── ai_agent/                   # Chunk 3: AI 工单分类
├── office_tools/               # Chunk 4: 办公支持工具
├── tests/                      # 自动化测试套件
├── docs/                       # 详细文档
└── scripts/                    # 通用工具脚本
```

# 约束条件
1. **所有配置文件使用环境变量**（从 .env 读取）
2. **PHP 代码遵循 PSR-12 规范**
3. **Shell 脚本添加 `set -euo pipefail`**
4. **每个文件头部添加文件用途注释**
5. **目录下创建 README.md 说明该模块职责**

# 特别要求
- **不要生成实际业务代码**，只生成空文件和目录结构
- 在每个目录的 README 中写明该模块对应 JD 的哪项需求
- docker-compose.yml 必须包含：
  - web 服务: Nginx + PHP 8.2 (官方镜像)
  - db 服务: MySQL 8.0 (数据卷持久化)
  - phpmyadmin 服务: 端口 8080
  - ollama 服务: 端口 11434 (为 Chunk 3 准备)
- .env.example 必须包含所有敏感配置的占位符

生成后，请输出"✅ 项目骨架已创建，包含 X 个目录和 Y 个配置文件"。

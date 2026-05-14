请实现 Chunk 1 - Magento Lite 产品平台。这是项目的核心，必须做到生产级质量。

# 角色设定
你是一个拥有 **8 年经验的 Magento/PHP 资深工程师**，熟悉高并发电商系统。你现在为 RedWolf 设计一个轻量级但可靠的产品展示和库存管理系统。

# 核心需求
RedWolf 使用 Magento/Shopify，你需要证明：
1. 能看懂并编写 PHP 电商代码
2. 理解库存并发控制
3. 能设计简单的 MVC 架构
4. 重视安全（CSRF、SQL 注入、XSS）

# 文件清单（必须逐一声明生成）

## 1.1 数据库层
**文件: `magento_lite/includes/Database.php`** - 单例模式 PDO 连接，错误处理，预处理语句

**文件: `magento_lite/includes/ProductModel.php`**
- getAllProducts(): 查询所有产品，按创建时间倒序，使用 idx_created 索引
- getProductById(int $id): 根据 ID 查询，使用主键索引
- updateStock(int $productId, int $quantity): **关键**，使用事务 + 行级锁 `SELECT ... FOR UPDATE` 防止并发超卖

## 1.2 购物车管理
**文件: `magento_lite/includes/CartManager.php`**
- addItem(int $productId, int $quantity): 检查库存 → 存入 session
- getCartTotal(string $currency = 'HKD'): 多货币转换（汇率从 .env 读取）
- getCartItems(): 返回购物车所有商品

## 1.3 主页面
**文件: `magento_lite/product.php`**
- 显示产品列表（12个产品，网格布局）
- 每个产品：图片、名称、价格（HKD/USD/CNY 三币种切换）、库存、加入购物车按钮
- AJAX 处理"加入购物车"请求，返回 JSON
- 使用 CSRF token 保护

## 1.4 前端脚本
**文件: `magento_lite/assets/script.js`**
- 货币切换（点击下拉框切换显示）
- 加入购物车 AJAX 请求（fetch API）
- **防抖**：库存查询间隔 >= 300ms
- Toast 提示（成功/失败）

## 1.5 API 端点
**文件: `magento_lite/api/update_stock.php`**
- 验证 CSRF token
- 验证输入（product_id, quantity 必须为正整数）
- 使用事务更新库存
- 返回 JSON：{success, message, new_stock}

## 1.6 数据库 Schema
**文件: `sql/schema.sql`**
创建 products 表（id, name, price_hkd, price_usd, price_cny, stock_qty, image_url, created_at），添加索引，插入 10 条 RedWolf 产品数据。

## 1.7 样式
**文件: `magento_lite/assets/style.css`**
响应式网格、产品卡片悬停效果、价格高亮、Toast 动画。

## 1.8 部署脚本
**文件: `deployment/chunk1-core.sh`**
- 检查 Docker
- 启动 web + db
- 等待 MySQL 就绪（30 秒超时）
- 导入 schema.sql
- 验证: curl http://localhost/product.php 返回 200
- 输出 ✅ Chunk 1 部署成功

## 1.9 测试脚本
**文件: `tests/chunk1/test_suite.sh`**
- 数据库连接
- 产品页 HTTP 200
- 数据完整性（至少 10 条产品）
- 库存 API 扣减正确性
- 并发安全性（5 个同时请求，库存不超卖）
- 全部通过返回 0，否则返回 1

## 1.10 文档
**文件: `docs/chunk1-architecture.md`**
- 为什么不用 Laravel？
- 数据库设计决策
- 并发控制实现
- 安全措施

# 输出要求
1. 所有文件使用 UTF-8 编码
2. PHP 代码添加 PHPDoc 注释
3. Shell 脚本添加错误检查
4. 每个函数不超过 50 行
5. 完成后运行部署脚本和测试脚本，确保全部通过

请开始生成。

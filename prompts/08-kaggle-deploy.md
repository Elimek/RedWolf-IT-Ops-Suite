# RedWolf Demo - Kaggle 云部署指南

## 优势
- ✅ 完全云端，不占用本地资源
- ✅ 免费 GPU（运行 Ollama）
- ✅ 持久化存储
- ✅ 可直接分享 Public URL 给面试官

## 步骤

### 1. 准备 Kaggle 账户
注册 https://www.kaggle.com（免费），验证邮箱。

### 2. 新建 Notebook
- 点击 "New Notebook"
- Settings: Internet Connected, GPU T4 x1, Save & Run All

### 3. 上传代码
```python
!git clone https://github.com/YOUR_USERNAME/RedWolf-Quick-Demo.git
%cd RedWolf-Quick-Demo
```

### 4. 安装依赖并启动
```python
!sudo service docker start
!docker-compose up -d
!sleep 30
!bash test.sh
```

### 5. 访问 Demo
Kaggle 自动创建 Public URL，或使用 ngrok 暴露端口：
```python
!pip install pyngrok
!from pyngrok import ngrok
!public_url = ngrok.connect(8080)
!print(public_url)
```

### 6. 演示给面试官
把 Kaggle Notebook 链接发给面试官。

## 注意事项
- Kaggle 免费 GPU 每周 30 小时
- 容器空闲 9 小时后自动暂停
- 建议使用 Kaggle Dataset 预存 Ollama 模型（避免首次下载耗时）

## 优化：使用预构建镜像
创建 `Dockerfile.kaggle` 预下载模型，然后：
```bash
!docker build -f Dockerfile.kaggle -t redwolf-demo .
!docker run -p 8080:80 redwolf-demo
```

## 备选方案
- **GitPod**: https://gitpod.io/#https://github.com/YOUR_USERNAME/RedWolf-Quick-Demo
- **Replit**: 创建 Docker-based Repl

## 推荐
按优先级：Kaggle > GitPod > Replit

---

**执行后，你的项目将完全云端运行，无需本地任何环境。**

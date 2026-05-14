# Interview Q&A Preparation

> Anticipated interview questions with prepared answers

---

## Q1: Why did you build this project?

**A:** This project directly addresses the requirements of the Assistant IT Officer position. Instead of just talking about my skills, I wanted to demonstrate them through working code. Each module maps to a specific responsibility in the job description: PHP e-commerce skills (Magento Lite), system monitoring, office IT support, and AI integration. It shows I can hit the ground running from day one.

---

## Q2: Why PHP instead of Python/Node.js?

**A:** The job description specifically mentions Magento/Shopify experience, which are PHP-based. Using PHP 8.2 demonstrates I can work with the company's existing technology stack. I also chose to write everything from scratch without Composer or any frameworks to show a deep understanding of PHP internals - PDO, sessions, CSRF, transactions - rather than relying on abstractions.

---

## Q3: Explain the concurrency control in the stock management system.

**A:** I use MySQL row-level locking with `SELECT ... FOR UPDATE` inside an explicit transaction. When a user adds an item to their cart, the system locks that specific product row, checks if enough stock exists, decrements it, then commits. If 5 users try to buy the last item simultaneously, only the first one to acquire the lock succeeds. The others get a "out of stock" message. This prevents the overselling problem that plagues many e-commerce systems.

---

## Q4: Why use a local LLM (Ollama) instead of ChatGPT API?

**A:** Two reasons: privacy and cost. As an IT officer handling internal support tickets, company data should never be sent to external APIs. Ollama runs entirely on-premise, so no data leaves the internal network. Additionally, there's zero API cost - the model runs on our own hardware. I also implemented a keyword-based fallback so the system works even when the AI service is unavailable.

---

## Q5: How would you monitor this system in production?

**A:** The monitoring module I built is designed for exactly this. It collects CPU, memory, disk, and network metrics every 60 seconds, stores them as JSONL files, and displays them on a real-time Chart.js dashboard. The alert engine checks thresholds (CPU > 90%, memory > 85%, disk > 90%) and sends notifications via email or webhook. There's also a cooldown mechanism to prevent alert spam.

---

## Q6: What security measures have you implemented?

**A:** Several layers:
- **CSRF Protection**: Token-based CSRF on all state-changing operations (cart, stock updates)
- **SQL Injection Prevention**: All database queries use PDO prepared statements with parameterized queries
- **Command Injection Prevention**: Input validation with `filter_var()` for IP addresses; private IP range restrictions on network tools
- **Authentication**: Session-based admin authentication for sensitive tools
- **Audit Logging**: Every admin action (network scans, fault simulation, VPN changes) is logged to the database
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, XSS Protection in Nginx config

---

## Q7: How would you handle a situation where the monitoring system detects high CPU?

**A:** The alert engine would trigger when CPU exceeds 90% for 3 consecutive readings (3 minutes). It would:
1. Write an alert to the database
2. Send an email notification (if SMTP is configured)
3. Call a webhook (if configured)
4. Apply cooldown (1 hour) to prevent spam

Then I'd SSH into the server, check `top` to identify the process, review logs, and take corrective action. The fault simulator in the monitoring module lets me practice exactly this scenario.

---

## Q8: What's the most challenging part of this project?

**A:** The AI ticket classifier was the most challenging. Designing a prompt template that consistently produces valid JSON output from an LLM requires careful engineering. I also had to implement a robust fallback system - if Ollama is slow or unavailable, the keyword classifier takes over seamlessly. Getting the accuracy above 85% with the keyword fallback alone required careful pattern design across 9 categories.

---

## Q9: How would you deploy this in RedWolf's environment?

**A:** The `deploy-all.sh` script handles the full deployment in about 5 minutes using Docker Compose. It starts all services, imports the database, and runs tests. For production, I'd additionally configure HTTPS via Let's Encrypt, set up database backups (daily cron), restrict phpMyAdmin to admin IPs, and enable fail2ban. The deployment guide covers three scenarios: Docker, traditional Ubuntu server, and Windows Server with WSL2.

---

## Q10: How would you extend this system?

**A:** Several directions:
1. **Real User Authentication**: Replace the simple auth system with a proper RBAC system
2. **WebSocket Updates**: Replace polling with WebSocket for real-time monitoring
3. **Mobile App**: REST API is already partially built - a mobile interface could be next
4. **Automated Remediation**: When alerts trigger, auto-run diagnostic scripts
5. **Historical Analytics**: Long-term trend analysis of system metrics
6. **Ticket System Integration**: Connect the AI classifier to an actual ticket system

---

## Q11: What's your experience with Magento specifically?

**A:** I designed the Magento Lite module to mirror Magento's architecture: an MVC-like pattern with Models (ProductModel, CartManager), Views (product.php), and Controllers (API endpoints). I implemented the same patterns Magento uses: session-based carts, multi-currency pricing, and concurrent-safe inventory management. While this is a simplified version, it demonstrates the same principles used in a full Magento installation.

---

## Q12: How do you handle a network issue reported by a non-technical employee?

**A:** The office tools are designed exactly for this. The user would:
1. Use the Network Scanner to verify their device is visible on the network
2. Check the Printer Config tool if it's a printer issue
3. Check VPN Status if they're working remotely

All tools have simple, non-technical interfaces. For anything beyond that, the audit logs would help me diagnose the issue remotely.

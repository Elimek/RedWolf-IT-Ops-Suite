<?php
declare(strict_types=1);
/**
 * RedWolf IT Officer Demo - Main Landing Page
 * Entry point for the complete IT Operations Suite
 */

session_start();

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RedWolf IT Officer Demo - Operations Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        :root {
            --rw-primary: #c8102e;
            --rw-dark: #1a1a2e;
            --rw-darker: #0f0f1a;
            --rw-accent: #e94560;
            --rw-text: #eee;
            --rw-muted: #888;
        }
        body {
            background: var(--rw-darker);
            color: var(--rw-text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .rw-header {
            background: linear-gradient(135deg, var(--rw-dark) 0%, #16213e 100%);
            border-bottom: 3px solid var(--rw-primary);
            padding: 2rem 0;
        }
        .rw-logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rw-primary);
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .rw-logo span {
            color: var(--rw-text);
        }
        .rw-subtitle {
            color: var(--rw-muted);
            font-size: 1.1rem;
            margin-top: 0.25rem;
        }
        .module-card {
            background: var(--rw-dark);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        .module-card:hover {
            border-color: var(--rw-primary);
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(200, 16, 46, 0.15);
        }
        .module-icon {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.25rem;
        }
        .module-card h3 {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 0.75rem;
        }
        .module-card p {
            color: var(--rw-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .module-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 1rem;
        }
        .stat-card {
            background: var(--rw-dark);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rw-primary);
        }
        .stat-label {
            color: var(--rw-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }
        .tech-badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 0.25rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--rw-text);
        }
        footer {
            background: var(--rw-dark);
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 1.5rem 0;
            color: var(--rw-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="rw-header">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <div class="rw-logo"><span>Red</span>Wolf</div>
                <div class="rw-subtitle">IT Officer Demo - Operations Suite</div>
            </div>
            <div class="text-end">
                <div class="tech-badge"><i class="bi bi-filetype-php"></i> PHP 8.2</div>
                <div class="tech-badge"><i class="bi bi-database"></i> MySQL 8.0</div>
                <div class="tech-badge"><i class="bi bi-ubuntu"></i> Linux</div>
                <div class="tech-badge"><i class="bi bi-robot"></i> Ollama AI</div>
            </div>
        </div>
    </div>
</header>

<!-- Stats Bar -->
<section class="py-4">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number">4</div>
                    <div class="stat-label">Modules</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">PHP/Shell Scripts</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">On-Premise</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number">0</div>
                    <div class="stat-label">External Deps</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Module Cards -->
<section class="py-4">
    <div class="container">
        <h2 class="mb-4 fw-bold" style="font-size:1.5rem;">System Modules</h2>
        <div class="row g-4">
            <!-- Chunk 1: Magento Lite -->
            <div class="col-md-6 col-lg-3">
                <a href="/magento_lite/product.php" class="module-card">
                    <div class="module-icon" style="background: rgba(233, 69, 96, 0.15); color: var(--rw-accent);">
                        <i class="bi bi-shop"></i>
                    </div>
                    <h3>E-Commerce Platform</h3>
                    <p>Magento Lite product showcase with multi-currency support, real-time inventory management, and AJAX-powered shopping cart.</p>
                    <span class="module-badge" style="background: rgba(233, 69, 96, 0.15); color: var(--rw-accent);">PHP + MySQL</span>
                </a>
            </div>

            <!-- Chunk 2: Monitoring -->
            <div class="col-md-6 col-lg-3">
                <a href="/monitoring/dashboard.php" class="module-card">
                    <div class="module-icon" style="background: rgba(46, 196, 182, 0.15); color: #2ec4b6;">
                        <i class="bi bi-activity"></i>
                    </div>
                    <h3>Server Monitoring</h3>
                    <p>Real-time system metrics dashboard with Chart.js visualizations, alert management, and fault simulation tools.</p>
                    <span class="module-badge" style="background: rgba(46, 196, 182, 0.15); color: #2ec4b6;">PHP + Shell</span>
                </a>
            </div>

            <!-- Chunk 3: AI Classifier -->
            <div class="col-md-6 col-lg-3">
                <a href="/ai_agent/classifier.html" class="module-card">
                    <div class="module-icon" style="background: rgba(72, 149, 239, 0.15); color: #4895ef;">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h3>AI Ticket Classifier</h3>
                    <p>Local LLM-powered ticket classification with privacy-first architecture. Zero external API calls, on-premise inference.</p>
                    <span class="module-badge" style="background: rgba(72, 149, 239, 0.15); color: #4895ef;">PHP + Python</span>
                </a>
            </div>

            <!-- Chunk 4: Office Tools -->
            <div class="col-md-6 col-lg-3">
                <a href="/office_tools/network_scanner.php" class="module-card">
                    <div class="module-icon" style="background: rgba(255, 183, 77, 0.15); color: #ffb74d;">
                        <i class="bi bi-tools"></i>
                    </div>
                    <h3>Office Tools</h3>
                    <p>Network scanner, printer configuration helper, VPN status checker, and PowerShell equivalents for Windows.</p>
                    <span class="module-badge" style="background: rgba(255, 183, 77, 0.15); color: #ffb74d;">PHP + PowerShell</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Architecture Overview -->
<section class="py-4 pb-5">
    <div class="container">
        <h2 class="mb-4 fw-bold" style="font-size:1.5rem;">Architecture Highlights</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card text-start">
                    <h5><i class="bi bi-shield-lock text-success me-2"></i>Security First</h5>
                    <p class="text-muted mb-0">CSRF protection, SQL injection prevention, command injection guards, audit logging on all sensitive operations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-start">
                    <h5><i class="bi bi-cpu text-info me-2"></i>Zero Dependencies</h5>
                    <p class="text-muted mb-0">No Composer, no NPM. All code written from scratch demonstrating deep understanding of PHP and system internals.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-start">
                    <h5><i class="bi bi-lock text-warning me-2"></i>Privacy by Design</h5>
                    <p class="text-muted mb-0">AI inference runs entirely on-premise via Ollama. No data leaves the internal network.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container text-center">
        <p class="mb-0">RedWolf IT Officer Demo Suite &copy; <?= date('Y') ?> | Built with PHP 8.2 + MySQL 8.0 + Linux</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

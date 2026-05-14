<?php
/**
 * Navbar include for Office Tools pages
 * RedWolf IT Ops Suite
 */
use RedWolf\OfficeTools\AuthManager;
AuthManager::init();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container-fluid">
        <a class="navbar-brand" href="/office_tools/">
            <i class="bi bi-shield-lock"></i> RedWolf IT Ops
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/office_tools/network_scanner.php">
                        <i class="bi bi-wifi"></i> Network Scanner
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/office_tools/printer_config.html">
                        <i class="bi bi-printer"></i> Printer Tools
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/office_tools/vpn_status.php">
                        <i class="bi bi-lock"></i> VPN Status
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if (AuthManager::checkAuth()): ?>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars(AuthManager::currentUser() ?? 'admin'); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/office_tools/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/office_tools/login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

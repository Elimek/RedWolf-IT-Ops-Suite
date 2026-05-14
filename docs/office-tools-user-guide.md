# RedWolf IT Ops Suite - Office Tools User Guide

## Introduction

This guide covers the IT Office Support Tools included in the RedWolf IT Ops Suite. These tools are designed to help office staff and IT administrators diagnose network issues, manage printers, and monitor VPN connections.

**Important:** The Network Scanner and VPN Status tools require administrator login credentials. Contact your IT administrator if you need access.

---

## Table of Contents

1. [Network Scanner](#network-scanner)
2. [Printer Tools](#printer-tools)
   - [IP Calculator](#ip-calculator)
   - [Port Tester](#port-tester)
   - [Error Code Lookup](#error-code-lookup)
   - [Driver Downloads](#driver-downloads)
3. [VPN Status Monitor](#vpn-status-monitor)
4. [Troubleshooting](#troubleshooting)
5. [FAQ](#faq)

---

## Network Scanner

### Overview

The Network Scanner allows you to discover devices on your office network. It can identify which computers, printers, and other devices are currently online, check for open ports, and resolve hostnames.

### How to Use

1. **Log in** with your administrator credentials when prompted.
2. **Set the IP range:**
   - **Start IP**: The first address to scan (e.g., `192.168.1.1`)
   - **End IP**: The last address to scan (e.g., `192.168.1.254`)
   - The default range covers the entire `/24` subnet.
3. **Click "Start Scan"** to begin.
4. **Watch the progress** bar as hosts are scanned. Results appear in real-time.
5. **Export results** by clicking the "Export CSV" button after the scan completes.

### Reading Results

| Column | Description |
|--------|-------------|
| IP Address | The network address of the device |
| Status | **Online** (green) or **Offline** (gray) |
| Hostname | The device name if available |
| Open Ports | Services detected (SSH, HTTP, RDP, etc.) |
| Response | How quickly the device responded |

### Common Port Definitions

- **SSH (22)**: Secure Shell - remote command-line access
- **HTTP (80)**: Web server
- **HTTPS (443)**: Secure web server
- **RDP (3389)**: Remote Desktop Protocol (Windows)
- **VNC (5900)**: Virtual Network Computing (screen sharing)

### Security Notes

- Only private IP ranges are allowed (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
- All scan activity is logged for audit purposes
- Maximum scan range: 512 hosts

---

## Printer Tools

The Printer Tools page has four tabs, each providing a different diagnostic utility. No login is required for these tools.

### IP Calculator

Calculate network information from an IP address and subnet mask.

**Steps:**

1. Click the **IP Calculator** tab.
2. Enter the **IP Address** (e.g., `192.168.1.100`).
3. Enter either the **Subnet Mask** (e.g., `255.255.255.0`) or the **CIDR** value (e.g., `24`).
4. Click **Calculate**.
5. View results including:
   - Network address
   - Broadcast address
   - Usable host range
   - Total number of hosts

<!-- Screenshot placeholder: IP Calculator with sample output -->

### Port Tester

Check if a printer's network port is reachable.

**Steps:**

1. Click the **Port Tester** tab.
2. Enter the **Printer IP Address**.
3. Enter the **Port Number** (default `9100` for most network printers).
4. Click **Test Connection**.
5. The tool will generate terminal commands you can run to test connectivity.

**Note:** Port 9100 is the standard "JetDirect" or "Raw" printing port used by most network printers.

<!-- Screenshot placeholder: Port Tester with command output -->

### Error Code Lookup

Search a database of common printer error codes from HP, Brother, and Canon.

**Steps:**

1. Click the **Error Code Lookup** tab.
2. **Search** by typing the error code or a description keyword.
3. **Filter by brand** using the dropdown menu (All / HP / Brother / Canon).
4. Review the **Solution** column for each error to resolve the issue.

**Common Error Examples:**

| Brand | Code | Description |
|-------|------|-------------|
| HP | 49.xxxx | Firmware error - restart printer |
| HP | 13.x | Paper jam - check all paper paths |
| Brother | Unable to Print 50 | Internal malfunction - check for obstructions |
| Canon | 5100 | Carriage error - clear obstruction |

<!-- Screenshot placeholder: Error Code Lookup with search results -->

### Driver Downloads

Find links to official printer driver download pages.

**Steps:**

1. Click the **Driver Downloads** tab.
2. Find your printer brand (HP, Brother, Canon, Epson, Xerox, Samsung).
3. Click the link to visit the manufacturer's official download page.
4. Search for your specific printer model on the manufacturer's website.

**Always download drivers from the manufacturer's official website** to avoid malware or incompatible software.

---

## VPN Status Monitor

### Overview

The VPN Status Monitor shows whether your computer is connected to the company VPN. It supports OpenVPN and WireGuard connections.

### How to Use

1. **Log in** with your administrator credentials.
2. The page automatically displays your VPN connection status.

### Status Display

| Indicator | Meaning |
|-----------|---------|
| Green dot | VPN is connected |
| Red dot (pulsing) | VPN is disconnected |
| VPN Type | Shows OpenVPN, WireGuard, or None |
| VPN IP | Your virtual IP address on the VPN |
| Public IP | Your external IP address |
| Received / Sent | Data traffic statistics |

### Actions

- **Reconnect VPN**: Attempts to re-establish the VPN connection.
- **Disconnect VPN**: Disconnects from the VPN.
- **View Logs**: Shows recent VPN log entries for troubleshooting.
- **Refresh**: Updates the status display.

<!-- Screenshot placeholder: VPN Status page showing connected state -->

---

## Troubleshooting

### Network Scanner Issues

**Problem:** Scan shows all hosts offline.

- Verify your computer is connected to the office network.
- Check that the IP range matches your network subnet.
- A firewall may be blocking scan requests. Contact IT.

**Problem:** Scan is very slow.

- Reduce the IP range (e.g., scan 192.168.1.1-50 instead of 1-254).
- Check for network congestion.

**Problem:** Cannot export CSV.

- Ensure at least one online host was found during the scan.
- Try refreshing the page and running the scan again.

### Printer Issues

**Problem:** Printer is not responding.

1. Check the printer is powered on and not in sleep mode.
2. Verify the network cable is connected (or Wi-Fi is connected).
3. Use the Port Tester to check if the printer's IP port is reachable.
4. Check the printer's IP address in its settings menu.

**Problem:** Paper jam error.

1. Open all printer access doors.
2. Gently remove any jammed paper.
3. Check for small torn pieces that may remain.
4. Close all doors and try printing again.

**Problem:** Print spooler keeps stopping (Windows).

Open PowerShell as Administrator and run:

```powershell
.\office_tools\windows_tools\PrinterConfig.ps1 -Action restart-spooler
```

### VPN Issues

**Problem:** VPN shows disconnected but you believe it should be connected.

1. Click **Refresh** to update the status.
2. Try clicking **Reconnect VPN**.
3. Check your network connection to the internet.

**Problem:** Cannot connect to VPN.

1. Verify your internet connection is working.
2. Try the Reconnect button.
3. Contact IT for VPN credentials or server status.

**Problem:** VPN connects but cannot access resources.

1. Verify you are connected to the correct VPN server.
2. Check with IT that your VPN access permissions are correct.
3. Try disconnecting and reconnecting.

---

## FAQ

**Q: Do I need to log in to use the Printer Tools?**
A: No. The Printer Tools (IP Calculator, Port Tester, Error Code Lookup, Driver Downloads) are accessible without login. Only Network Scanner and VPN Status require authentication.

**Q: Can I scan any IP range?**
A: No. For security reasons, only private IP ranges are allowed: 10.x.x.x, 172.16-31.x.x, and 192.168.x.x.

**Q: What is my printer's IP address?**
A: You can usually find it on the printer's display panel under Network Settings, or by printing a network configuration page. You can also use the Network Scanner to discover it.

**Q: The error code for my printer is not listed. What do I do?**
A: Search for the exact error code on the printer manufacturer's official support website. The Driver Downloads tab has links to all major manufacturers.

**Q: How do I get VPN access?**
A: Contact your IT administrator to request VPN credentials and setup instructions.

**Q: Are scan results saved?**
A: All scan activity is logged for security auditing. You can export individual scan results as CSV files for your records.

**Q: Can I use these tools from home?**
A: The Network Scanner only works on the local network. The Printer Tools and Driver Downloads work from any location. VPN Status requires being on the network where the VPN server is accessible.

**Q: How long does a network scan take?**
A: A full /24 subnet scan (254 hosts) typically completes in under 30 seconds, depending on network conditions.

---

## Contact

For IT support, contact your system administrator or submit a ticket through the company help desk system.

*RedWolf IT Ops Suite - Office Tools Module*

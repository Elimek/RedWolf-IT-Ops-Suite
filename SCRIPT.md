# 3-Minute Demo Script

> Precise timing for interview demonstration

---

## Setup (Before Starting)
- [ ] Docker services running (`docker compose up -d`)
- [ ] Browser open to http://localhost
- [ ] Terminal open with project directory
- [ ] Have VALUE.md printed/ready to hand over

---

## 0:00 - 0:15 | Introduction

**[Camera: Full screen - browser showing landing page]**

"Hi, I'm here to demonstrate the RedWolf IT Operations Suite I built. This is a complete IT management platform designed specifically for the Assistant IT Officer role. Let me walk you through the four modules."

**[Action: Point to the 4 module cards on the landing page]**

---

## 0:15 - 0:50 | Module 1: E-Commerce Platform (35 sec)

**[Camera: Click "E-Commerce Platform" card]**

"This is a lightweight Magento-style product management system. As you can see, we have a product grid with 12 RedWolf airsoft products."

**[Action: Scroll through products, hover over a card]**

"Each product displays pricing in three currencies - HKD, USD, and CNY - with a currency switcher at the top."

**[Action: Click currency dropdown, switch to USD]**

"Adding to cart is handled via AJAX with CSRF token protection for security."

**[Action: Click "Add to Cart" on a product, show toast notification]**

"Behind the scenes, stock management uses MySQL row-level locking to prevent overselling during concurrent requests. This is critical for any e-commerce operation."

**[Action: Open browser DevTools Network tab, show the AJAX POST response]**

---

## 0:50 - 1:30 | Module 2: Server Monitoring (40 sec)

**[Camera: Click browser back, then click "Server Monitoring"]**

"The monitoring module provides real-time system visibility. We see CPU, memory, and disk usage with color-coded status indicators."

**[Action: Point to the circular progress bars and Chart.js graph]**

"Network traffic is displayed as a live chart updated every 60 seconds. Below, we can see the top 10 processes consuming resources."

**[Action: Scroll to process table]**

"The alert engine monitors thresholds - if CPU exceeds 90% for 3 consecutive readings, it automatically triggers an alert with email or webhook notification. There's also a built-in fault simulator for testing alert responses."

**[Action: Click to fault simulator if time permits, or mention it]**

---

## 1:30 - 2:10 | Module 3: AI Ticket Classifier (40 sec)

**[Camera: Navigate to AI Classifier]**

"This is the AI ticket classification system. It uses a local large language model running on-premise via Ollama - no data ever leaves our network."

**[Action: Click a sample ticket, e.g., "The printer on 3rd floor is jamming"]**

"When I click classify, the system sends the ticket to our local LLM, which returns a category, confidence score, reasoning, and priority level."

**[Action: Click "Classify" button, wait for result]**

"You can see it correctly identified this as a 'printer' issue with high confidence. The system also has a keyword-based fallback in case the AI service is unavailable."

**[Action: Show the history table below]**

"All classifications are logged for audit purposes. The accuracy benchmark with 50 labeled samples shows over 85% accuracy."

---

## 2:10 - 2:45 | Module 4: Office Tools (35 sec)

**[Camera: Navigate to Office Tools > Network Scanner]**

"The office tools module provides practical IT support utilities. The network scanner can ping-scan an entire /24 network and check common ports."

**[Action: Enter IP range, click scan - or show a pre-cached result]**

"The printer configuration helper includes an IP calculator, port tester, error code lookup for HP/Brother/Canon printers, and driver download links - all designed for non-technical staff."

**[Action: Switch to printer_config.html tab]**

"There's also a VPN status checker and Windows PowerShell equivalents for each tool."

---

## 2:45 - 3:00 | Closing (15 sec)

**[Camera: Back to landing page or terminal]**

"Everything runs in Docker with one command: `bash deploy-all.sh`. The entire test suite passes, and there's comprehensive documentation."

**[Action: Hand over printed VALUE.md]**

"This document shows the quantified business value - approximately HK$35,000 per month in operational savings with a 686% ROI."

**[Action: Show GitHub URL or terminal]**

"All code is open source and available for review. I can explain any component or make modifications on the spot. Thank you."

---

## Key Phrases to Remember
- "Row-level locking prevents overselling"
- "Data never leaves the network"
- "Zero external API costs"
- "One command deployment"
- "HK$35,000/month savings, 686% ROI"

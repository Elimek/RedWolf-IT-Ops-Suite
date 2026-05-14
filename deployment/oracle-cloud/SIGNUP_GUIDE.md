# Oracle Cloud Free Tier - Registration Guide (Hong Kong)

## Why Oracle Cloud?

- **4 ARM cores + 24GB RAM** - permanently free
- Enough to run the entire RedWolf IT Ops Suite (5 Docker containers + Ollama)
- $0/month, no credit card charges if you stay within free tier
- Hong Kong identity card (HKID) accepted for verification

---

## Step 1: Sign Up (15 minutes)

1. Go to: https://www.oracle.com/cloud/free/
2. Click **"Start for free"**
3. Fill in:
   - **Country/Region**: Hong Kong
   - **Name**: Your legal name (must match HKID)
   - **Email**: Use your personal email
   - **Password**: Strong password
4. **Account type**: Choose your home region closest to HK:
   - `ap-tokyo-1` (Tokyo) - recommended, low latency from HK
   - `ap-seoul-1` (Seoul)
   - `ap-chuncheon-1` (Chuncheon)
   - `ap-osaka-1` (Osaka)
5. Click **"Verify my email"** and check your inbox

## Step 2: Identity Verification (5 minutes)

1. Upload a photo of your **HKID card** (front side)
2. Take a **selfie** with your phone
3. Fill in your **address** (any HK address works)
4. Phone verification: Enter the SMS code sent to your HK phone number

## Step 3: Payment Card (required but NOT charged)

Oracle requires a credit/debit card for verification, but:
- **Free tier is NOT charged** as long as you stay within limits
- Use any Visa/Mastercard debit card with $1 balance
- A temporary $1 authorization hold will appear and disappear

Cards that work:
- HK bank debit cards (HSBC, Hang Seng, BOA, etc.)
- Prepaid Visa/Mastercard with $1+ balance

## Step 4: Wait for Approval (5-60 minutes)

- Most accounts are approved within 10 minutes
- Some may take up to 24 hours
- You'll receive an email when approved

## Step 5: Create Your Instance

Once approved, log in to OCI Console:

### 5.1 Create VCN (Virtual Cloud Network)

1. Navigate to **Networking > Virtual Cloud Networks**
2. Click **Create VCN**
3. Name: `redwolf-vcn`
4. CIDR block: `10.0.0.0/16`
5. Click **Create VCN**

### 5.2 Open Ingress Ports in Security List

1. In the VCN, click **Subnets > default > Security Lists > default**
2. Click **Add Ingress Rules** and add these rules:

| Source CIDR | Protocol | Port | Purpose |
|-------------|----------|------|---------|
| 0.0.0.0/0 | TCP | 22 | SSH access |
| 0.0.0.0/0 | TCP | 80 | Web (Nginx) |
| 0.0.0.0/0 | TCP | 8080 | phpMyAdmin |
| 0.0.0.0/0 | TCP | 11434 | Ollama API |

### 5.3 Create SSH Key Pair

On your **Windows** machine, open PowerShell:

```powershell
ssh-keygen -t ed25519 -f $env:USERPROFILE\.ssh\redwolf-oracle -N ""
```

Copy the **public key** content:
```powershell
Get-Content $env:USERPROFILE\.ssh\redwolf-oracle.pub
```

### 5.4 Create Compute Instance

1. Navigate to **Compute > Instances**
2. Click **Create Instance**
3. Configure:
   - **Name**: `redwolf-demo`
   - **Image**: Ubuntu 22.04 (Canonical) - ARM
   - **Shape**: `VM.Standard.A1.Flex` (Ampere ARM)
   - **OCPU count**: 4
   - **Memory (GB)**: 24
   - **Boot volume**: 50 GB
   - **SSH public key**: Paste the public key from step 5.3
   - **VCN**: redwolf-vcn
   - **Subnet**: Public subnet
4. Click **Create**

### 5.5 Get Public IP

1. After instance is created (1-2 minutes), click on it
2. Under **Instance information**, find **Public IP address**
3. Note this IP - this is your server address

---

## Step 6: Connect and Deploy

From your Windows machine:

```powershell
ssh -i $env:USERPROFILE\.ssh\redwolf-oracle ubuntu@<YOUR_PUBLIC_IP>
```

Then run the deployment script (see `init-server.sh` and `deploy.sh`).

---

## Troubleshooting

### Can't SSH?
```bash
# Check Security List has port 22 open
# Check instance is in "Running" state
# Try with verbose:
ssh -vvv -i ~/.ssh/redwolf-oracle ubuntu@<IP>
```

### Instance stuck in "Provisioning"?
- ARM free tier instances sometimes queue. Wait 5-10 minutes.
- If stuck >30 min, terminate and recreate.

### Account not approved?
- Check spam folder for verification email
- Try signing up with a different email
- Contact Oracle Support via the free tier page

### Want to avoid credit card?
- Not possible - Oracle requires it for all accounts
- Use a prepaid card with minimal balance

---

## Cost Warning

**Always Free resources include:**
- 1 ARM instance (4 OCPUs, 24GB RAM)
- 200GB block storage total
- 10TB/month outbound data

**You will be charged if:**
- You create additional instances beyond free tier
- You use Intel/AMD shapes (only ARM is free)
- You exceed 200GB total boot volume
- You use additional services (Object Storage, Load Balancer, etc.)

**To check usage:**
1. OCI Console > Billing > Cost Analysis
2. Set "Usage" to see current month

**To avoid charges:**
- Only use `VM.Standard.A1.Flex` shape
- Keep boot volume at or under 50GB
- Don't create additional resources

---

## Estimated Timeline

| Step | Time |
|------|------|
| Registration | 15 min |
| ID verification | 5 min |
| Card verification | 5 min |
| Account approval | 10-60 min |
| Instance creation | 5 min |
| Server setup + deploy | 10 min |
| **Total** | **~50 min - 1.5 hrs** |

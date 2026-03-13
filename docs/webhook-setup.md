# BTCPay Server Webhook Setup Guide

This guide will help you configure the webhook so that enrollment happens automatically after payment.

## Prerequisites

- BTCPay Server is running and accessible
- Moodle site is accessible (or use a tunnel for local development)
- You have admin access to both systems

## Step 1: Determine Your Moodle Webhook URL

Your webhook URL will be: `https://YOURMOODLE/payment/gateway/btcpay/webhook.php`

### For Local Development (Docker)

If your Moodle is running locally (e.g., `http://localhost:8000`), BTCPay Server cannot reach it directly. You have two options:

**Option A: Use a Tunnel (Recommended for Testing)**

1. Install [ngrok](https://ngrok.com/) or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/)

Expose your local Moodle. Use the **same port** as in your `env` (`MOODLE_DOCKER_WEB_PORT`). The container serves **HTTP** (even when the host port is 443), so you **must** use `http://` in the ngrok command so ngrok connects to the backend with HTTP:

```bash
 # If MOODLE_DOCKER_WEB_PORT=443 — MUST use "http://localhost:443" (not just 443 or https://)
 ngrok http http://localhost:443

 # If MOODLE_DOCKER_WEB_PORT=8000 (default in moodle-docker)
 ngrok http 8000
```

1. Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)
2. Your webhook URL will be: `https://abc123.ngrok.io/payment/gateway/btcpay/webhook.php`

### For Production

Use your production Moodle URL:

- Example: `https://moodle.yourschool.edu/payment/gateway/btcpay/webhook.php`

## Step 2: Configure Webhook in BTCPay Server

1. **Log in to BTCPay Server**
  - Go to your BTCPay Server admin panel
2. **Navigate to Store Settings**
  - Click on your store
  - Go to **Settings** → **Webhooks** (or **Store Settings** → **Webhooks**)
3. **Create a New Webhook**
  - Click **"Create a new webhook"** or **"Add webhook"**
  - **Payload URL**: Enter your webhook URL from Step 1
    - Example: `https://yourmoodle.com/payment/gateway/btcpay/webhook.php`
  - **Secret**: Click **"Generate secret"** or enter a custom secret
    - **IMPORTANT**: Copy this secret immediately - you'll need it for Moodle!
    - Example secret: `abc123xyz789...` (long random string)
4. **Subscribe to Events**
  Select these events (at minimum):
  - ✅ **Invoice created**
  - ✅ **Invoice received payment**
  - ✅ **Invoice settled** (CRITICAL - this triggers enrollment)
  - ✅ **Invoice invalid**
  - ✅ **Invoice expired**
5. **Save the Webhook**
  - Click **"Save"** or **"Create webhook"**

## Step 3: Configure Moodle Gateway Settings

1. **Log in to Moodle as Admin**
2. **Navigate to Payment Gateway Settings**
  - Go to **Site administration** → **Plugins** → **Enrolments** → **Payment gateways**
  - Or: **Site administration** → **Payment accounts**
  - Click on your payment account
  - Find **BTCPay Server** in the gateway list
  - Click the settings icon (⚙️) or **"Configure"**
3. **Enter Webhook Secret**
  - **Webhook Secret**: Paste the secret you copied from BTCPay Server (Step 2, #3)
  - This must match exactly - any difference will cause webhook verification to fail
4. **Set Fulfill Order Status**
  - **Fulfill Order Status**: Select **"Settled"** (default)
    - This means enrollment happens when BTCPay sends a "Settled" event
    - Alternative: "Processing" if you want enrollment earlier (less secure)
5. **Other Settings**
  - **BTCPay Server Base URL**: Your BTCPay Server URL (e.g., `https://btcpay.example.com`)
  - **Store ID**: Your BTCPay store ID
  - **API Key**: Your BTCPay API key
  - **Invoice Expiration**: How long invoices are valid (default: 60 minutes)
6. **Save Settings**
  - Click **"Save changes"**

## Step 4: Verify Webhook Configuration

### Check Webhook Secret Match

1. In BTCPay Server: Go to **Store Settings** → **Webhooks** → Your webhook
2. Check the **Secret** field (you may need to regenerate if you forgot it)
3. In Moodle: Go to gateway settings and verify the **Webhook Secret** matches exactly

### Test Webhook Reachability

You can test if BTCPay can reach your webhook URL:

```bash
# Test from command line (replace with your webhook URL)
curl -X POST https://yourmoodle.com/payment/gateway/btcpay/webhook.php \
  -H "Content-Type: application/json" \
  -H "BTCPay-Sig: sha256=test" \
  -d '{"test": "data"}'
```

Expected response:

- `403 Forbidden` (because signature is invalid, but webhook is reachable)
- `400 Bad Request` (if JSON is invalid, but webhook is reachable)
- If you get connection errors, the webhook is not reachable

## Step 5: Test the Complete Flow

1. **Make a Test Payment**
  - Go to a course with payment enrollment
  - Click "Enrol now" or purchase
  - Select BTCPay Server
  - Complete payment in BTCPay
2. **Check Webhook Delivery**
  - In BTCPay Server: Go to **Store Settings** → **Webhooks** → Your webhook
  - Click on the webhook to see delivery logs
  - Look for recent deliveries with status **200 OK**
  - If you see **403 Forbidden**, the webhook secret doesn't match
  - If you see connection errors, the webhook URL is not reachable
3. **Check Enrollment**
  - After payment is "Settled" in BTCPay
  - Wait a few seconds for webhook to process
  - Check if you're enrolled in the course
  - If not enrolled, check the troubleshooting section below


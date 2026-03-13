# Moodle-BTCPayServer

Development environment for the Moodle BTCPay Server payment gateway plugin (paygw_btcpay).

## Quick Start

1. **Clone and bootstrap** (from the `moodle-btcpayserver` directory):
   ```bash
   git clone <repository-url>
   cd moodle-btcpayserver
   cp env.example env    # optional: edit env for MOODLE_BRANCH, ngrok, etc.
   make bootstrap
   ```

2. Open **http://localhost:8000** and complete Moodle installation (admin password defaults to `test` in dev).

3. Install the plugin: **Site administration → Notifications** and follow the prompts for paygw_btcpay.

## Commands

| Command | Description |
|---------|-------------|
| `make bootstrap` | Initial setup (Moodle, Docker, symlink, start) |
| `make up` / `make start` | Start containers |
| `make down` / `make stop` | Stop containers |
| `make reset` | Stop and remove volumes |
| `make reset-full` | Full wipe (.moodle and .dev/moodle-docker) |
| `make logs` | Follow webserver logs |
| `make shell` | Bash in webserver container |
| `make purge-caches` | Purge Moodle caches |
| `make upgrade` | Run Moodle upgrade (plugins) |
| `make help` | List all targets |

## Documentation

- **[Webhook setup](docs/webhook-setup.md)** — Configure BTCPay webhooks so enrollment happens automatically after payment.
- **[Development](docs/development.md)** — Public URL (ngrok), Moodle branch, reset, and environment details.

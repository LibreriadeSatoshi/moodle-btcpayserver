# Development Environment

This project uses the official [moodlehq/moodle-docker](https://github.com/moodlehq/moodle-docker) setup for local development.

## How It Works

The bootstrap script:

- Clones Moodle core into `.moodle/` (gitignored)
- Clones moodlehq/moodle-docker into `.dev/moodle-docker/` (gitignored)
- Creates a symlink from `.moodle/<PLUGIN_PATH>` to the repository root (for host-side tooling)
- Configures Docker volumes so the plugin code is available in the container
- Sets up the Moodle configuration file for Docker

The plugin code is mounted into the container, so code changes are reflected immediately without rebuilding containers.

## Using a Public URL (e.g. ngrok for webhooks)

If you use ngrok (or another tunnel) so BTCPay Server can reach your Moodle webhook, redirects must use the public URL instead of localhost:

1. Edit `**env**` (at repo root; copy from `env.example` if missing) and set:
  ```bash
   MOODLE_DOCKER_WEB_HOST=your-subdomain.ngrok-free.app
   MOODLE_DOCKER_WEB_PORT=443
   MOODLE_DOCKER_WEB_SCHEME=https
  ```
2. Re-run bootstrap so the container gets these variables:
  ```bash
   make bootstrap
  ```
3. Or just restart the webserver after editing env:
  ```bash
   make restart
  ```

Moodle’s `$CFG->wwwroot` will then be `https://your-subdomain.ngrok-free.app`, so redirects and webhook URLs use the public URL.

## Changing Moodle Branch

To switch to a different Moodle version:

1. Edit `env` and change `MOODLE_BRANCH` to the desired branch (e.g., `MOODLE_411_STABLE`).
2. Remove the existing Moodle core: `rm -rf .moodle`
3. Re-run bootstrap: `make bootstrap`

## Resetting the Environment

To completely reset and start fresh:

```bash
make reset-full
make bootstrap
```

- `**make reset**` — Stops containers and removes volumes; run `make bootstrap` again to start.
- `**make reset-full**` — Same as reset plus deletes `.moodle` and `.dev/moodle-docker` (full wipe).


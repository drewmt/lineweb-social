# Docker evaluation environment

The included Compose stack is the fastest reproducible way to evaluate Lineweb
Social locally. It runs the application, MariaDB, the queue worker, and the
scheduler with one command:

```bash
./bin/docker-setup
```

The setup script generates a local `.env.docker` with a unique application key
and database password, builds the locked PHP and JavaScript dependencies, runs
the migrations, waits for the health endpoint, and then exposes the application
only on `http://127.0.0.1:8080`.

## Security and persistence boundaries

- `.env.docker` is mode-restricted and ignored by Git. Never commit or share it.
- MariaDB is reachable only inside the Compose network; it has no published
  host port.
- Application containers run without root privileges, drop Linux capabilities,
  use a read-only filesystem, and retain only the writable cache and storage
  volumes.
- Database records and private media use named volumes, so a normal stop or
  `docker compose down` does not delete them.
- Mail is logged instead of delivered. Configure and verify a transactional
  provider before inviting real members.

This stack uses Laravel's development HTTP server and is intended for local
evaluation and controlled development only. It is not the production deployment
recipe. A public deployment still needs HTTPS, a production web server, tested
backups and restore, monitoring, mail delivery, and an explicit rollback plan.

## Day-to-day commands

All commands must use the generated environment file:

```bash
# Status
docker compose --env-file .env.docker ps

# Logs
docker compose --env-file .env.docker logs --follow app worker scheduler

# Stop without deleting data
docker compose --env-file .env.docker down

# Rebuild after updating the source
docker compose --env-file .env.docker up --build --detach
```

To run an Artisan command inside the application image:

```bash
docker compose --env-file .env.docker run --rm app artisan about
```

## Reset the evaluation data

The following command permanently deletes the Compose database, uploaded media,
sessions, and caches. Use it only when a full reset is intentional:

```bash
docker compose --env-file .env.docker down --volumes
```

Remove `.env.docker` afterwards only if you also want the next setup to generate
a new application key and database password.

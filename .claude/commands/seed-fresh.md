---
description: Wipe the database, run all migrations, then seed
allowed-tools: Bash
---

Reset the dev database. Before running, verify the container is local-only — never run this against prod.

Use the `webserver` service from docker-compose.yml (the compose service name, not `DOCKER_ROUTER`):

```sh
docker compose exec webserver php artisan migrate:fresh --seed
```

Then run `composer typescript` so the Wayfinder TS types pick up any schema-driven type changes.

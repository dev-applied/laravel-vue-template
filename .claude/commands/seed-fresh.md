---
description: Wipe the database, run all migrations, then seed
allowed-tools: Bash
---

Reset the dev database. Before running, verify the container is local-only — never run this against prod.

Use the webserver container from docker-compose.yml (named after `DOCKER_ROUTER` in `.env`):

```sh
docker compose exec $DOCKER_ROUTER php artisan migrate:fresh --seed
```

Then run `composer typescript` so the Wayfinder TS types pick up any schema-driven type changes.

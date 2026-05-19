---
description: Regenerate Wayfinder TS types from Laravel routes/controllers
allowed-tools: Bash
---

Regenerate the frontend TS types that mirror Laravel routes and controllers. Run this after any change to `routes/api.php`, a controller signature, or a FormRequest.

```sh
docker compose exec $DOCKER_ROUTER composer typescript
```

That runs `php artisan wayfinder:generate --path=./resources/ts/types/laravel`. Show the user which files changed under `resources/ts/types/laravel/` so they can confirm the diff matches their backend changes.

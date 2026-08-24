---
description: Run pest in the webserver container, optionally with a filter
allowed-tools: Bash
argument-hint: [filter]
---

Run the Pest test suite inside the webserver container.

If `$ARGUMENTS` is empty, run the whole suite in parallel:

```sh
docker compose exec webserver ./vendor/bin/pest --parallel
```

Otherwise pass `$ARGUMENTS` as a `--filter`:

```sh
docker compose exec webserver ./vendor/bin/pest --filter="$ARGUMENTS"
```

Report failures with the test name and the assertion that failed. If a Feature test fails, suggest the most likely cause based on the assertion (auth, validation, response shape) before guessing at the fix.

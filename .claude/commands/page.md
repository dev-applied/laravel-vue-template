---
description: Scaffold a new Vue page (uses stubs/vue-make-page.stub) and register it in the router
allowed-tools: Bash, Read, Edit
argument-hint: <PageName>
---

Scaffold a new Vue page named `$ARGUMENTS`.

1. Run `docker compose exec webserver php artisan vue:make-page` and pass `$ARGUMENTS` as the page name. This writes `resources/ts/pages/$ARGUMENTS.vue` from `stubs/vue-make-page.stub`.
2. Register the new page in `resources/ts/router/index.ts` (or the appropriate sub-router) using the project's RouteDesigner DSL. Match the existing routes' middleware pattern (Authentication / Guest etc.) and add the route name to `resources/ts/router/paths.ts` if a `ROUTES` constant is appropriate.
3. Show the user the diff and the URL the new page will live at.

Do not generate any URLs or paths beyond what's defined in the router DSL — read the existing routes for the pattern.

---
type: infrastructure-index
---

# Infrastructure

| Environment | URL | Stack | File |
|-------------|-----|-------|------|
| local | localhost (Docker) | [stack] | [local.md](local.md) |
| dev | [url] | [stack] | [dev.md](dev.md) |
| staging | [url] | [stack] | [staging.md](staging.md) |
| production | [url] | [stack] | [production.md](production.md) |

Integrations: [integrations.md](integrations.md)

---
Access policy: never store raw secrets in `.claude/context/`. SSH access via `~/.ssh/config.d/<project-slug>` (host alias `<slug>-<env>`); no DB credentials stored — query via the app's CLI on the box. Raw secret found = incident → rotate.

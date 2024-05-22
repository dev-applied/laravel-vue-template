import type { ModuleNamespace } from "vite/types/hot"

import.meta.glob<true, string, ModuleNamespace>("./*.ts", { eager: true })

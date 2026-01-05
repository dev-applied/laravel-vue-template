import type {AutocompleteItem, PrimitiveId} from "./types"

/**
 * Ensures a value is always returned as an array.
 */
export function ensureArray<T>(value: T | T[] | null | undefined): T[] {
  if (Array.isArray(value)) return value
  if (value === null || value === undefined) return []
  return [value]
}

/**
 * Coerces a value into a string safely.
 */
export function safeToString(val: any): string {
  if (val === null || val === undefined) return ""
  return String(val)
}

/**
 * Deep omit utility.
 * Removes given keys from any nested structure.
 */
export function deepOmit<T extends Record<string, any>>(
  obj: T,
  keys: string[]
): T {
  if (!obj || typeof obj !== "object") return obj

  const result: any = Array.isArray(obj) ? [] : {}

  Object.keys(obj).forEach((key) => {
    if (keys.includes(key)) return

    const val = obj[key]
    if (typeof val === "object" && val !== null) {
      result[key] = deepOmit(val, keys)
    } else {
      result[key] = val
    }
  })

  return result
}

/**
 * Safe equality check for strings/numbers.
 */
export function isEqualId(a: PrimitiveId, b: PrimitiveId): boolean {
  return safeToString(a) === safeToString(b)
}

/**
 * Normalize items to ensure unique IDs and consistent typing.
 */
export function normalizeItems<T = any>(
  items: AutocompleteItem<T>[]
): AutocompleteItem<T>[] {
  return items.map((item) => ({
    ...item,
    id: item.id, // nothing special, but in place for future transformations
  }))
}

/**
 * Extract ID field from an item using itemValue property or default "id".
 */
export function extractId<T = any>(
  item: AutocompleteItem<T>,
  itemValue: string = "id"
): PrimitiveId {
  return (item as any)[itemValue]
}

/**
 * Extract display label using itemTitle property.
 */
export function extractTitle<T = any>(
  item: AutocompleteItem<T>,
  itemTitle: any
): string {
  if (!itemTitle) return safeToString(item.name ?? item.id)

  if (typeof itemTitle === "string") {
    return safeToString((item as any)[itemTitle])
  }

  if (typeof itemTitle === "function") {
    return safeToString(itemTitle(item))
  }

  return safeToString(item.name ?? item.id)
}

/**
 * Groups items by a resolver function or field.
 * Returns a sorted array of groups: [{ header: 'A', items: [...] }, ...]
 */
export function groupItems<T = any>(
  items: AutocompleteItem<T>[],
  groupBy: any
): Array<{ header: string; items: AutocompleteItem<T>[] }> {
  if (!groupBy) {
    return [{ header: "", items }]
  }

  const isFn = typeof groupBy === "function"
  const groups: Record<string, AutocompleteItem<T>[]> = {}

  for (const item of items) {
    const rawVal = isFn ? groupBy(item) : (item as any)[groupBy]
    // Default to 'Other' if the key is null/undefined/empty
    const key = rawVal || 'Other'
    const strKey = safeToString(key)

    if (!groups[strKey]) groups[strKey] = []
    groups[strKey].push(item)
  }

  // Sort groups alphabetically to ensure consistent order
  const sortedKeys = Object.keys(groups).sort((a, b) => a.localeCompare(b))

  return sortedKeys.map((header) => ({
    header,
    items: groups[header],
  }))
}

/**
 * Merge selected items into the main items list, ensuring selected items
 * appear first and without duplication. Uses `itemValue` to compare ids.
 */
export function mergeSelectedIntoItems<T = any>(
  items: AutocompleteItem<T>[],
  selected: AutocompleteItem<T>[],
  itemValue: string = 'id'
): AutocompleteItem<T>[] {
  const output = [...items]

  for (const sel of [...selected].reverse()) {
    const selId = extractId(sel, itemValue)
    const exists = output.some((x) => isEqualId(extractId(x, itemValue), selId))
    if (!exists) output.unshift(sel)
  }

  return output
}


export function toKebabCase (str = '') {
  if (toKebabCase.cache.has(str)) return toKebabCase.cache.get(str)!
  const kebab = str
    .replace(/[^a-z]/gi, '-')
    .replace(/\B([A-Z])/g, '-$1')
    .toLowerCase()
  toKebabCase.cache.set(str, kebab)
  return kebab
}
toKebabCase.cache = new Map<string, string>()

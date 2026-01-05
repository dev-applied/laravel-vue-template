// components/AppAutoComplete/types.ts

export type PrimitiveId = string | number;

/**
 * The raw item returned from the API.
 * T is the shape of your entity (e.g. Size, Category, User).
 */
export interface AutocompleteItem<_T = any> {
  [key: string]: any;
  id: PrimitiveId;
  // Other fields come from T
}

/**
 * Standard Laravel paginator response shape.
 */
export interface LaravelPaginatorResponse<T = any> {
  current_page: number;
  data: AutocompleteItem<T>[];
  last_page: number;
  per_page: number | string;
  total: number;

  // Laravel includes many additional keys, but we don't care about them.
  // Including them here for completeness but not used by pagination logic.
  first_page_url?: string | null;
  last_page_url?: string | null;
  next_page_url?: string | null;
  prev_page_url?: string | null;
  from?: number | null;
  to?: number | null;
  path?: string;
  links?: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

/**
 * The shape returned by your fetch composable.
 */
export interface PaginatedResult<T> {
  items: AutocompleteItem<T>[];
  currentPage: number;
  lastPage: number;
  total: number;
  perPage: number;
}

/**
 * Shape used to POST a new custom item.
 */
export interface CreateItemPayload {
  [key: string]: any;
  name?: string;
}

/**
 * How `addCustomItem()` resolves created records.
 */
export interface CreateItemResponse<T = any> {
  [key: string]: any;
  newItem?: AutocompleteItem<T>;
}

/**
 * Function signature for computing display text.
 */
export type ItemTitleResolver<T> =
  | keyof AutocompleteItem<T>
  | ((item: AutocompleteItem<T>) => string);

/**
 * Grouping function or field.
 */
export type GroupByResolver<T> =
  | keyof AutocompleteItem<T>
  | ((item: AutocompleteItem<T>) => string);

/**
 * Shared options for API search calls.
 */
export interface SearchQueryParams {
  search: string;
  page: number;
  itemsPerPage: number;
  filters?: Record<string, any>;
  excludeIds?: PrimitiveId[];
}

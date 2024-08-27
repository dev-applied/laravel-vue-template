export type BaseTableProps = {
  endpoint: string;
  filters?: Record<string, any>;
  method?: "POST" | "GET" | "PUT" | "PATCH";
  noDataText?: string;
  itemsPerPage?: number;
}

export type AppListTableProps = BaseTableProps & {
  showSearchBar?: boolean;
  showFilterBar?: boolean;
  threeLine?: boolean;
  twoLine?: boolean;
  scrollable?: boolean;
  height?: string | number;
}

export type AppPaginationTableProps = BaseTableProps & {
  itemsPerPageOptions?: number[];
  striped?: boolean;
}

export type AppTableProps = AppListTableProps & AppPaginationTableProps

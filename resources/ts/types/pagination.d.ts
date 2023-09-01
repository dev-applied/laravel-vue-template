declare namespace App {
  export type PaginationDirection = "DESC" | "ASC"

  export interface Pagination {
    sort: string | null
    direction: PaginationDirection
    page: number
    perPage: number
    count: number
  }
}

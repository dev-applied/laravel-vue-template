import { StoreDefinition } from "pinia"

export default function usePaginationQuery(apiUrl: string, options: any) {
  interface Props {
    endpoint: string;
    fieldName: string | null;
    additionalRequestData: Record<string, any>;
    method: "POST" | "GET" | "PUT" | "PATCH";
    enableSearch: boolean;
    enableFilter: boolean;
    storeModule: StoreDefinition | null;
    threeLine: boolean;
    twoLine: boolean;
    scrollable: boolean;
    height: string | number;
    noDataText: string | null;
    itemsPerPage: number | null;
  }

  const defaultProps = {
    fieldName: null,
    additionalRequestData: () => ({}),
    method: "GET",
    enableSearch: false,
    enableFilter: false,
    storeModule: null,
    threeLine: false,
    twoLine: false,
    scrollable: true,
    height: "100%",
    noDataText: null,
    itemsPerPage: null
  }

  const props = withDefaults(defineProps<Props>(), defaultProps)


  return {
    props
  }
}

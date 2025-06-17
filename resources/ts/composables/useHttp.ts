import {$http} from "@/plugins/axios"
import {$error} from "@/plugins/errorHandler"

export default function useHttp() {
  return {
    $http,
    $error
  }
}

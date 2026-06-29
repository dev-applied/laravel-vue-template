import routeTo from '@/plugins/routeTo.ts'
import {ROUTES} from "@/router/route-names"

export default function useAuth() {
  return {
    $routeTo: routeTo,
    ROUTES
  }
}

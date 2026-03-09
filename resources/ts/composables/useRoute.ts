import routeTo from '@/plugins/routeTo.ts'
import {ROUTES} from "@/router/paths"

export default function useAuth() {
  return {
    $routeTo: routeTo,
    ROUTES
  }
}

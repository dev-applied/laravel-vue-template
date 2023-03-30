import { useUserStore } from '@/stores/user'

export default async function authentication(): Promise<void> {
  const userStore = useUserStore()
  await userStore.loadUser()
}

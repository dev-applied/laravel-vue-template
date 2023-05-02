import type { MiddlewareCaller, MiddlewareConstructor } from '@/types'
import type { NavigationGuardNext, Route } from 'vue-router/types/router'

export default class Pipeline {
  protected pipes: MiddlewareConstructor[] = []

  private through(...pipes: MiddlewareConstructor[]) {
    this.pipes = pipes.flat()

    return this
  }

  public async handle(to: Route, from: Route, next: NavigationGuardNext) {
    this.through(...(to.meta?.middleware ?? []))
    const final: MiddlewareCaller = async (): Promise<void> => {
      next()
    }

    const pipeline = this.pipes
      .reverse()
      .reduce<(to: Route, from: Route, cancel: NavigationGuardNext) => Promise<void>>(
        // @ts-ignore
        this.carry(),
        final
      )

    // @ts-ignore
    await pipeline(to, from, next)
  }

  protected carry() {
    return (next: MiddlewareCaller, middleware: MiddlewareConstructor) => {
      return async (to: Route, from: Route, cancel: NavigationGuardNext) => {
        await new middleware().handle(to, from, next, cancel)
      }
    }
  }
}

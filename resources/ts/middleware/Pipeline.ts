import type {NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded} from "vue-router"

export default class Pipeline {
  protected pipes: App.Middleware.Constructor[] = []

  public async handle(
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: NavigationGuardNext
  ) {
    this.through(...(to.meta?.middleware ?? []))

    const final: App.Middleware.Caller = async (): Promise<void> => {
      next()
    }

    const pipeline = this.pipes
      .reverse()
      .reduce<App.Middleware.Caller>(this.carry(), final)

    await pipeline(to, from, next)
  }

  protected carry() {
    return (next: App.Middleware.Caller, middleware: App.Middleware.Constructor): App.Middleware.Caller => {
      return async (
        to: RouteLocationNormalized,
        from: RouteLocationNormalizedLoaded,
        cancel: NavigationGuardNext
      ) => {
        await new middleware().handle(to, from, next, cancel)
      }
    }
  }

  private through(...pipes: App.Middleware.Constructor[]) {
    this.pipes = pipes.flat()

    return this
  }
}

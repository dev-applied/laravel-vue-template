import type { NavigationGuardNext, Route } from "vue-router/types/router"

export default class Pipeline {
  protected pipes: App.Middleware.Constructor[] = []

  public async handle(to: Route, from: Route, next: NavigationGuardNext) {
    this.through(...(to.meta?.middleware ?? []))
    const final: App.Middleware.Caller = async (): Promise<void> => {
      next()
    }

    const pipeline = this.pipes
      .reverse()
      .reduce<(to: Route, from: Route, cancel: NavigationGuardNext) => Promise<void>>(
        this.carry(),
        final
      )

    // @ts-ignore
    await pipeline(to, from, next)
  }

  protected carry() {
    return (next: App.Middleware.Caller, middleware: App.Middleware.Constructor) => {
      return async (to: Route, from: Route, cancel: NavigationGuardNext) => {
        await new middleware().handle(to, from, next, cancel)
      }
    }
  }

  private through(...pipes: App.Middleware.Constructor[]) {
    this.pipes = pipes.flat()

    return this
  }
}

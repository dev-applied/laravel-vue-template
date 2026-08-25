import RouteDesigner from "@/router/RouteDesigner"
import Authentication from "@/middleware/Authentication"
import Authorization from "@/middleware/Authorization.ts"
import ForceTypes from "@/middleware/ForceTypes"

export const ROUTES = {
  TASKS: "tasks.index",
  TASK_BOARD: "tasks.board",
}

RouteDesigner.group('', function () {
  RouteDesigner.route(
    "/tasks",
    () => import("@modules/Tasks/resources/ts/pages/TasksPage.vue"),
    ROUTES.TASKS
  )

  // The board only exists in the list+kanban variant. import.meta.glob rather
  // than a bare dynamic import: the `list` choice deletes the file, and a
  // static path to a missing module fails the whole build.
  const board = import.meta.glob('/modules/Tasks/resources/ts/pages/TaskBoardPage.vue')
  const boardPath = '/modules/Tasks/resources/ts/pages/TaskBoardPage.vue'

  if (board[boardPath]) {
    RouteDesigner.route("/tasks/board", board[boardPath] as never, ROUTES.TASK_BOARD).title("Task board")
  }
})
  .layout("Default")
  .middleware([ForceTypes, Authentication, Authorization])
  .props()

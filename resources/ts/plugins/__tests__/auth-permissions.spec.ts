// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest"

// $auth reads the user off the pinia store; stub the store rather than standing
// up pinia, since these tests are about the permission maths only.
const { user } = vi.hoisted(() => ({ user: { value: null as any } }))

vi.mock("@/stores/user", () => ({
  useUserStore: () => ({
    get user() { return user.value },
    set user(v: any) { user.value = v },
  }),
}))

import { $auth } from "@/plugins/auth"

describe("$auth permission helpers", () => {
  beforeEach(() => { user.value = null })

  it("fails closed for a guest instead of throwing", () => {
    // Authorization.ts calls these on every gated navigation. They were
    // commented out while still being called, which threw a TypeError.
    expect(() => $auth.hasPermission("items.view")).not.toThrow()
    expect($auth.hasPermission("items.view")).toBe(false)
    expect($auth.hasAnyPermissions(["items.view"])).toBe(false)
    expect($auth.hasAllPermissions(["items.view"])).toBe(false)
  })

  it("fails closed when the user carries no all_permissions key", () => {
    // A project without the RolesPermissions module never sends this key.
    user.value = { id: 1, first_name: "Ada" }

    expect($auth.hasAnyPermissions(["items.view"])).toBe(false)
    expect($auth.hasAllPermissions(["items.view"])).toBe(false)
  })

  it("reads spatie's all_permissions shape", () => {
    user.value = { id: 1, all_permissions: [{ name: "items.view" }, { name: "items.edit" }] }

    expect($auth.hasPermission("items.view")).toBe(true)
    expect($auth.hasPermission("items.delete")).toBe(false)
  })

  it("hasAnyPermissions needs one match, hasAllPermissions needs every match", () => {
    user.value = { id: 1, all_permissions: [{ name: "items.view" }] }

    expect($auth.hasAnyPermissions(["items.view", "items.delete"])).toBe(true)
    expect($auth.hasAllPermissions(["items.view", "items.delete"])).toBe(false)
    expect($auth.hasAllPermissions(["items.view"])).toBe(true)
  })

  it("flattens nested permission arrays", () => {
    // The signature accepts string[][] — route meta merges groups.
    user.value = { id: 1, all_permissions: [{ name: "a" }, { name: "b" }] }

    expect($auth.hasAllPermissions([["a"], ["b"]])).toBe(true)
    expect($auth.hasAnyPermissions([["z"], ["b"]])).toBe(true)
  })

  it("an empty requirement list is vacuously satisfied", () => {
    user.value = { id: 1, all_permissions: [] }

    expect($auth.hasAllPermissions([])).toBe(true)
    expect($auth.hasAnyPermissions([])).toBe(false)
  })
})

// @vitest-environment jsdom
import {describe, expect, it} from "vitest"
import {defineComponent, h} from "vue"
import {mount} from "@vue/test-utils"
import {readFileSync} from "node:fs"
import {resolve} from "node:path"

/**
 * Regression guard for a bug that blanked entire pages.
 *
 * Several kernel components forward every slot to the component they wrap:
 *
 *   <template v-for="(_, name) in $slots" #[name]="slotData">
 *     <slot :name="name" v-bind="slotData" />
 *   </template>
 *
 * Vuetify invokes some slots with NO arguments (`no-data`, `loading`, `top`,
 * `bottom`, ...). `slotData` is then `undefined`, Vue's `guardReactiveProps`
 * turns `undefined` into `null`, and `renderSlot(slots, name, props = {})`
 * only applies its default for `undefined` — so `null` reaches `props.key`
 * and throws. The throw happens during render, so the whole page goes blank,
 * not just the slot.
 *
 * `v-bind="slotData || {}"` is the fix.
 *
 * Vue fixed the underlying throw upstream (observed fixed on 3.5.41). The
 * guard still ships: package.json allows `vue: ^3.5.0`, so any resolved
 * version below the fix still blanks the page without it.
 */

/** Stands in for a Vuetify component that calls a slot with no arguments. */
const SlotCallerStub = defineComponent({
  name: "SlotCallerStub",
  setup(_, {slots}) {
    return () => h('div', [slots['no-data']?.()])
  },
})

function mountWithNoDataSlot(forwardExpression: string) {
  const SlotForwarder = defineComponent({
    name: 'SlotForwarder',
    components: {SlotCallerStub},
    template: `
      <SlotCallerStub>
        <template v-for="(_, name) in $slots" #[name]="slotData">
          <slot :name="name" v-bind="${forwardExpression}" />
        </template>
      </SlotCallerStub>
    `,
  })

  return mount(SlotForwarder, {
    slots: {'no-data': '<span class="empty">Nothing here</span>'},
  })
}

describe('slot forwarding', () => {
  it('survives a child invoking a forwarded slot with no arguments', () => {
    expect(mountWithNoDataSlot('slotData || {}').find('.empty').exists()).toBe(true)
  })

  it('renders the same guarded or not, because Vue fixed the throw upstream', () => {
    // This used to assert `mountWithNoDataSlot('slotData')` throws
    // /Cannot read propert/ — the exact error that blanked pages.
    //
    // Vue fixed it upstream: as of 3.5.41 the unguarded form no longer throws
    // and produces byte-identical output to the guarded one. Asserting a throw
    // here now fails, so this pins the CURRENT behaviour instead.
    //
    // The guard is NOT thereby cargo cult, and must stay:
    //   - every Vue below the fix still throws, and the template targets a
    //     range (`vue: ^3.5.0`), not a single pinned version;
    //   - the source-level assertions below are the real regression guard.
    expect(mountWithNoDataSlot('slotData').innerHTML)
      .toBe(mountWithNoDataSlot('slotData || {}').innerHTML)
  })

  // Every component that forwards slots this way must use the guarded form.
  // A future "simplification" back to `v-bind="slotData"` reintroduces a
  // page-blanking bug that no unit test of that component alone would catch.
  it.each([
    'AppPaginationTable.vue',
    'AppTable.vue',
    'AppDialog.vue',
    'fields/AppTextField.vue',
    'fields/AppTextarea.vue',
    'fields/AppMaskField.vue',
  ])('%s guards its forwarded slot props', (file) => {
    const source = readFileSync(resolve(__dirname, '..', file), 'utf8')

    expect(source).toContain('v-bind="slotData || {}"')
    expect(source).not.toContain('v-bind="slotData"\n')
  })
})

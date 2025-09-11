import type {EffectScope, Ref, WatchSource} from 'vue'
import {computed, effectScope, type ModelRef, onScopeDispose, ref, toRaw, watch} from 'vue'

export function useProxiedModel<T>(modelRef: ModelRef<T>, defaultValue: any = null) {
    const internal = ref(modelRef.value === undefined ? defaultValue : modelRef.value ) as Ref<typeof modelRef.value>
    const isControlled = computed(() => {
        return modelRef.value !== undefined
    })

    toggleScope(() => !isControlled.value, () => {
        watch(() => modelRef.value, val => {
            internal.value = val
        })
    })

    return computed<typeof modelRef.value>({
        get() {
            return isControlled.value ? modelRef.value : internal.value
        },
        set(internalValue) {
            const newValue = internalValue
            const value = toRaw(isControlled.value ? modelRef.value : internal.value)
            if (value === newValue || value === internalValue) {
                return
            }
            internal.value = newValue
            modelRef.value = newValue
        },
    })
}


function toggleScope (source: WatchSource<boolean>, fn: (reset: () => void) => void) {
    let scope: EffectScope | undefined
    function start () {
        scope = effectScope()
        scope.run(() => fn.length
            ? fn(() => { scope?.stop(); start() })
            : (fn as any)()
        )
    }

    watch(source, active => {
        if (active && !scope) {
            start()
        } else if (!active) {
            scope?.stop()
            scope = undefined
        }
    }, { immediate: true })

    onScopeDispose(() => {
        scope?.stop()
    })
}

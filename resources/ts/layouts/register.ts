import Vue from "vue";
import type { ModuleNamespace } from 'vite/types/hot';

const layouts = import.meta.glob<true, string, ModuleNamespace>('./*.vue', {eager: true});

Object.entries(layouts).forEach(([, layout]) => {
    Vue.component(layout?.default?.name, layout?.default);
});

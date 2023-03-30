import type {RouteConfig} from "vue-router";

export type RoutePath = RouteConfig & {page: string, children?: RoutePath[]}

const paths: RoutePath[] =  [
    { path: '/', page: 'LoginPage', name: 'Login', meta: { layout: 'Empty'}},
    { path: '/dashboard', page: 'DashboardPage', meta: { layout: 'Empty'}},
    { path: '*', page: 'Error404', name: 'Not Found', meta: { layout: 'Empty' } },
]

export default paths

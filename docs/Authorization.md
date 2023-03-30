## Authorization

### Laravel Side

On the laravel side we utilize Laravel Permission to manage authorization. You can read about
it [here](https://github.com/spatie/laravel-permission)

### Vue Side

On the vue side authorization can be handled in component, or globally via the route.

#### Router

Via the route you must define `permissions_all` or a `permissions_any` on the meta attribute of the route like:

````
const routes = [
    { path: '/admin/dashboard', page: 'AdminDashboard', meta: { permissions_all: ['Admin']} },
]    
````

The string values in the array will be matched against the permissions the user has in laravel.

#### In Component

In the component you can check the logged in users permissions by one of the following methods:

- `this.$auth.hasPermission(permission: string): boolean`
- `this.$auth.hasAnyPermissions(permissions: string[] | string[][]): boolean`
- `this.$auth.hasAllPermissions(permissions: string[] | string[][]): boolean`

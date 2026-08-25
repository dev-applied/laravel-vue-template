# Example

The reference module. Its twelve files ARE the documentation — this file only
says what to read them for.

It exists so the conventions have somewhere to be true rather than described:
a migration, a model, a factory, a controller, a form request, an API route, a
service provider, a Vue page, a routes.ts, and a test. Nothing here is a feature
anybody wants; `example_notes` is a table with a body and a timestamp.

**Read it in this order.** `ModuleServiceProvider.php` first — it is the only
file the kernel loads directly, and everything else is reached from it.
Then `Routes/api.php`, then the controller and its request, then
`resources/ts/routes.ts` and the page. That path is the whole contract a module
has with the template.

**Copy its shape, not its content.** `php artisan module:make` generates the
same skeleton from stubs, which is the faster start; this module is what you
compare against when the generated one does something you did not expect.

**Remove it from a client project.** It ships in the template bundle on purpose
and earns nothing in production:

```sh
rm -rf modules/Example
php artisan migrate:rollback   # if example_notes was already migrated
```

No options, no dependencies, no seam. If you find yourself adding one, add it to
a real module instead — a reference that grows features stops being readable in
one sitting, which is the only thing it is for.

import * as Laravel from '@/types/laravel/types'

declare global {
  export import App = Laravel.App
  export import Controllers = Laravel.App.Http.Controllers
}

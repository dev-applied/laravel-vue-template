## Axios

### General Access

Basic documentation on axios can be found [here](https://axios-http.com/docs/intro)

We assign the axios instance to a global prototype for more convient usage it can be accessed:

#### In Component

`this.$http`

#### Outside Component

`import axios from @/plugins/axios`

### Global Overrides

- Axios will automatically send the JWT token in the bearer authorization header when the user has be logged in
- Axios will automatically translate to JSON
- Axios will redirect to the route named `Login` in your `resources/ts/router/path.js` when the response `401` and the
  response message is `Authentication is required to continue` this is the traditional response if you throw a `UnauthorizedException` in Laravel or you have setup permission or authentication guards
- Axios will automatically have a base url from the .env parameter `VITE_API_BASE_URL`

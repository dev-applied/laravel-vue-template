## Error Handler

Error handler is a helper to handle error or validation responses from Laravel. It's traditional usage is as follows:

````
this.loading = true
const {data: {posts, message, errors}, status} = await this.$http.get('/posts', {}).catch(e => e)
this.loading = false
if (this.$error(status, message, errors)) return
this.posts = posts
````

It requires on `status` which is the HTTP status code but takes an optional `message` parameter which is a string and
the common response format when throwing exceptions in Laravel and an optional array `errors` which is the traditional
response parameter when validation fails in Laravel

<p align="center"><a href="https://appliedimagination.com" target="_blank"><img src="https://laravelvuespa.com/preview-dark.png" width="400" alt="Laravel Logo"></a></p>


## Installation

### Docker
- Install Docker Reverse Proxy
- Set `DOCKER_DOMAIN` in your `.env` to the domain you would like access the site on like `laravel-vue-template.site` 
- Set `DOCKER_ROUTER` in your `.env` to the name of your project like `laravel-vue-template` this name can have no `.` in it
- Run docker-compose up -d
- Access your site at `https://{DOCKER_DOMAIN}`
- If you are utilizing Horizon for the project uncomment line 33 - 48 in the docker file and run docker-compose up -d

### Local Server
- Install Local Server According to your OS guidelines or download MAMP/XAMPP
- Point your web server to the `{PROJECT_ROOT}/public` directory of the project



## About Laravel Vue Template

This is the Applied Imagination template for Laravel/Vue single site 

- [Authentication Configured](docs/Authentication.md).
- [Authorization Configured](docs/Authorization.md).
- [Docker Compose Configured](docs/Docker.md).
- [Eslint and PHP Codesniffer Configured](docs/Linting.md).



## Default Laravel Packages

- **[JWT Auth](https://github.com/tymondesigns/jwt-auth)**
- **[Laravel Permissions](https://github.com/spatie/laravel-permission)**
- **[Laravel Settings](https://github.com/spatie/laravel-settings)**
- **[Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper)**
- **[Horizon](https://laravel.com/docs/10.x/horizon)**
- **[Who Did It]()**

## Default Vue Packages

- **[Auth](docs/Auth.md)**
- **[Axios](docs/Axios.md)**
- **[Error Handler](docs/ErrorHandler.md)**
- **[Vuetify](https://v2.vuetifyjs.com/en/getting-started/installation/)**
- **[Pinia](https://pinia.vuejs.org/getting-started.html)**
- **[Notify](https://github.com/renoguyon/vuejs-noty)**

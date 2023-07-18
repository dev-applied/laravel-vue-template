<p align="center"><a href="https://appliedimagination.com" target="_blank"><img src="https://laravelvuespa.com/preview-dark.png" width="400" alt="Laravel Logo"></a></p>

 - [Installation](#installation)
   - [Docker](#docker)
   - [Local Server](#local-server)
 - [About Laravel Vue Template](#about-laravel-vue-template)
   - [Default Laravel Packages](#default-laravel-packages)
   - [Default Vue Packages](#default-vue-packages)
 - [Deployment](#deployment)

## Installation
 - [Docker](#docker)
 - [Local Server](#local-server)

### Docker
- Install [Traefik Dockerized](https://github.com/Devin345458/traefik-dockerized)
- Set `DOCKER_DOMAIN` in your `.env` to the domain you would like access the site on like `laravel-vue-template.localhost` 
- Set `DOCKER_ROUTER` in your `.env` to the name of your project like `laravel-vue-template` this name can have no `.` in it
- Run docker-compose up -d
- Access your site at `https://{DOCKER_DOMAIN}` i.e `https://laravel-vue-template.localhost`
- If you are utilizing Horizon for the project uncomment line 33 - 48 in the docker file and run docker-compose up -d

### Local Server
- Install a local AMP (Apache, Mysql, PHP) server according to your OS guidelines or download MAMP/XAMPP
- Point your web server to the `{PROJECT_ROOT}/public` directory of the project



## About Laravel Vue Template

This is the Applied Imagination template for Laravel/Vue single site 

- [Authentication Configured](docs/Authentication.md).
- [Authorization Configured](docs/Authorization.md).
- [Docker Compose Configured](docs/Docker.md).
- [Eslint and PHP Codesniffer Configured](docs/Linting.md).



### Default Laravel Packages

- **[JWT Auth](https://github.com/tymondesigns/jwt-auth)**
- **[Laravel Permissions](https://github.com/spatie/laravel-permission)**
- **[Laravel Settings](https://github.com/spatie/laravel-settings)**
- **[Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper)**
- **[Horizon](https://laravel.com/docs/10.x/horizon)**
- **[Who Did It]()**

### Default Vue Packages

- **[Auth](docs/Auth.md)**
- **[Axios](docs/Axios.md)**
- **[Error Handler](docs/ErrorHandler.md)**
- **[Vuetify](https://v2.vuetifyjs.com/en/getting-started/installation/)**
- **[Pinia](https://pinia.vuejs.org/getting-started.html)**
- **[Notify](https://github.com/renoguyon/vuejs-noty)**

## Deployment

By default, this project is setup to utilize Github Actions. It uses a workflow file for releases for the ``release/*`` branch pattern. Then a single deployment for the ``dev`` and ``staging`` and ``main`` branches. You can read more about these deployment flows and how to set them up [here](https://github.com/dev-applied/deploy-action)

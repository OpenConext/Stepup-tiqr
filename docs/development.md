Development environment
======================

The purpose of the development environment is only for running the different test and metric tools.

To get started, first setup the development environment. The development 'env' is a virtual machine. Every task described is run
from that machine.  

Requirements
-------------------
- Vagrant 2.2.x
    - vagrant-hostsupdater (1.1.1.160, global, optional)
    - vagrant-vbguest (0.19.0, global)
- Virtualbox
- Composer

Install
-------------------

### 1. Create virtual machine

``` cd homestead ``` 
 
``` composer install ```

Go back to root of the project (```cd ..```) 

``` vagrant up ```

If everything goes as planned you can develop inside the virtual machine

``` vagrant ssh ```

### 2. Build frontend assets:

``` yarn install ```

``` yarn encore dev ```

``` ./bin/console assets:install ```

### 3. Create configuration files

Copy and configure:
 
```cp .env.vm .env```

```cp config/packages/parameters.yml.dist config/packages/parameters.yml```

If everything goes as planned you can go to:

[https://tiqr.test](https://tiqr.example.com)

### Development

All frond-end logic is written in sass and typescript. You can run a watcher to update these automatically

Debugging
-------------------
Xdebug is configured when provisioning your development Vagrant box. 
It's configured with auto connect IDE_KEY=phpstorm and ```xon``` on cli env. 

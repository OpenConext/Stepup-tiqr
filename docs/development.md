Development environment
======================

The purpose of the development environment is only for running the different test and metric tools.

To get started, first setup the development environment. The development 'env' is a Docker container. 
Every task described is run from that machine.  

Requirements
-------------------
- Docker with Docker Compose

Install
-------------------
The purpose of the development environment is only for running the different test and metric tools.

To get started, first setup the development environment. The development environment is a docker container. That is
controlled via the [OpenConext-devconf](https://github.com/OpenConext/OpenConext-devconf/) project.

Every task described below should be run from that container.

### Development

All frond-end logic is written in sass and typescript. You can run a watcher to update these automatically

Debugging
-------------------
Xdebug is configured when provisioning your development Vagrant box. 
It's configured with auto connect IDE_KEY=phpstorm and ```xon``` on cli env. 

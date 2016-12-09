## ResourceContracts Elasticsearch Dockerfile

This repository contains the Dockerfile for [ResouceContracts Elasticsearch component](https://github.com/younginnovations/resourcecontracts-elasticsearch) for Docker.

### Base Docker Image

[Ubuntu 14.04](http://dockerfile.github.io/#/ubuntu)

### Installation

1. Install [Docker](https://www.docker.com/).
2. Clone this repo `git clone https://github.com/younginnovations/docker-rc-elasticsearch.git`
3. Go to the cloned folder `docker-rc-elasticsearch`
5. Build an image from Dockerfile `docker build -t=rc-index .`

### Usage

* Run `docker run -p 80:80 -d rc-index`
* Access the system from the browser at http://xxx/rc-index/

### TODO

* Update the apache configuration so that the system could be accessed from the base IP http://xxx 
* Mount the system temporary folder to the host folder to preserve the temporary files and logs
* Currently system is run using root, need to use appropriate users for running the servers and applications.
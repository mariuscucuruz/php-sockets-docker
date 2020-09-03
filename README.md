## PHP Sockets on Docker

#### Prerequisites
Install Docker CE, Docker Compose(, PHP v7+).
<hr />

#### Instructions
###### With Docker:
Run `docker-compose up` to spin up the containers. 

The first time you run this Docker will also build the image before starting the container; to manually build the image you can run `docker-compose build` (or `docker-compose up --build`).

The container will start attached to the current terminal session and, if you close it (i.e. `CTRL + C`), will terminate the container.
To start the container in the background (i.e. detached from terminal session) use the `-d` flag like so: `docker-compose up -d`.

> ###### Note:
> If you've made any changes to the container and need to force rebuild you can run `docker-compose up -d --build --force-recreate`. 
>
> You might also need to delete any cached layers Docker has used to build your setup. The following is a chain of commands to clear all cached layers (images, containers, volumes, networks):
>
> `docker image prune && docker volume prune && docker container prune && docker network prune && docker system prune --all`

###### PHP:
Run `php php/socket-server.php` to get the socket listening on localhost. Leave this running. 

You should now be able to `telnet localhost <PORT>` and test server on a separate terminal session.

Run `php php/socket-client.php` to get a client to connect to the server already running. You should see a `ping/pong` exchange between the client and server.

You may stop both client and server now (`CTRL`/`CMD` + `C`).
<hr />

#### SSH into the container 
To connect to the container via SSH you instruct Docker to attach a Bash (or any Shell) session to the container name as specified in the `docker-compose.yml` file under `container_name` like so:
`docker exec -it socketclient bash`

Get the IP address(es) of the container by running the following: 
`docker inspect socketclient | grep "IPAddress"`

#### To Do
Do we want the VPN running at boot time?

<hr />

## About the setup
This will create a network of VMs where a manager connects to a worker that is connected to a VPN via PHP socket .

#### Terminology
A `worker` is the VM that connects to the `proxy`. It acts as a socket client connecting to the available socket servers (proxies).

A `proxy` is a VM that runs a VPN and a socket server awaiting connections.

#### Workflow
As service priority goes, the `worker` is dependent on at least one `proxy`. 
A `proxy` runs VyprVPN on `openvpn256`, as well as a server awaiting connections on a specific socket.
The `worker` connects to `proxy`, confirms the VPN is running, sends specific commands and awaits response.

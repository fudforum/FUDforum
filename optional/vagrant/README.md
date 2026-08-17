# FUDforum Vagrant Build

A simple Vagrant-based development environment for **FUDforum** using Ubuntu, Nginx, MariaDB, PHP, and VirtualBox.

The environment is automatically configured using the included provisioning scripts.

## Requirements

* [Vagrant](https://www.vagrantup.com/)
* [VirtualBox](https://www.virtualbox.org/)
* [Git](https://git-scm.com/) (optional)

## Project Structure

At a minimum, the following three files are required:

```text
.
├── Vagrantfile
├── server_setup.sh
└── forum_setup.sh
```

However, we recommend checking out the entire FUDforum repository from GitHub:

```bash
git clone https://github.com/fudforum/FUDforum.git
cd FUDforum/extra/vagrant/
```

This ensures that all files required by the FUDforum build and development environment are available.

## Getting Started

Start the VM from the directory containing the `Vagrantfile`:

```bash
vagrant up
```

Provisioning will:

* Create an Ubuntu VM.
* Install Nginx, MariaDB, PHP, and required extensions.
* Configure the web server.
* Create the FUDforum database.
* Install and configure FUDforum.

Once provisioning is complete, access FUDforum at:

```text
http://localhost:8080/
```

The VM is also available on the private network at:

```text
http://192.168.56.50/
```

## Default Credentials

The development installation uses predefined database and administrator credentials configured by `server_setup.sh` and `forum_setup.sh`.

**These credentials are intended for development only and should be changed for any non-local deployment.**

## Useful Commands

```bash
# Start
vagrant up

# SSH into the VM
vagrant ssh

# Re-run provisioning
vagrant provision

# Restart
vagrant reload

# Stop
vagrant halt

# Destroy
vagrant destroy
```

## Rebuild

To completely recreate the environment:

```bash
vagrant destroy -f
vagrant up
```

This is useful when testing changes to the Vagrant configuration or provisioning scripts.

## Development Notes

This environment is intended for **local development and testing**, not production use.

The provisioning scripts configure the complete application stack automatically, making it possible to create a fresh FUDforum environment with a single `vagrant up` command.

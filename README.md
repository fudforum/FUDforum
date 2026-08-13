# FUDforum

**Fast. Uncompromising. Discussion.**

A powerful, scalable and highly customizable open-source discussion forum platform.

## ✨ Overview

**FUDforum** is a high-performance, scalable discussion forum system designed for communities that want complete control over their discussion platform.

It provides a comprehensive set of forum features while remaining highly customizable and suitable for everything from small communities to large, active discussion sites.

FUDforum supports unlimited users, forums, posts, topics, polls and attachments. It can also import XML feeds and integrate with **Usenet groups and mailing lists**, including bidirectional synchronization.

## 🚀 Features

* ⚡ **High performance** — built with scalability and speed in mind
* 📈 **Scalable architecture** — designed to handle growing communities
* 🎨 **Highly customizable** — adapt the forum to your community and workflow
* 🔎 Effective search engine — quickly find relevant posts and discussions across your forum
* 👥 **Unlimited users** — no artificial limits on community size
* 💬 **Unlimited forums, topics and posts**
* 📎 **File attachments**
* 📊 **Polls**
* 📰 **XML feed importing**
* 🌐 **Usenet integration**
* ✉️ **Mailing-list integration**
* 🔄 **Bidirectional synchronization** with supported external discussion systems
* 🛠️ **Web-based installation and upgrade tools**
* 🖥️ **Command-line installation and upgrade support**
* 🔧 **Administration and consistency-checking tools**

## 📦 Installation

FUDforum includes an installation wizard that guides you through the initial configuration.

### Quick start

Download the required installation files:

```bash
wget https://raw.githubusercontent.com/fudforum/FUDforum/master/install.php
wget https://raw.githubusercontent.com/fudforum/FUDforum/master/fudforum_archive
```

Copy the files to a web-accessible directory on your server and open:

```text
https://your-domain.example/install.php
```

The installation wizard will guide you through five configuration steps.

> **Tip:** It is recommended to place the installation files in the same directory where you intend to install FUDforum.

For the complete installation procedure, see the [Installation Guide](https://github.com/fudforum/FUDforum/wiki/Installation).

## 🔄 Upgrading

To upgrade an existing FUDforum installation, download:

```bash
wget https://raw.githubusercontent.com/fudforum/FUDforum/master/upgrade.php
wget https://raw.githubusercontent.com/fudforum/FUDforum/master/fudforum_archive
```

Copy the files to your forum's web directory and open:

```text
https://your-domain.example/upgrade.php
```

After the upgrade, FUDforum will launch its consistency checker. If it does not start automatically, run the consistency checker manually.

See the [Upgrade Guide](https://github.com/fudforum/FUDforum/wiki/Upgrading) for detailed instructions.

## 🗑️ Uninstallation

FUDforum also provides an `uninstall.php` utility.

Before performing an uninstall:

1. Open `uninstall.php` in a text editor.
2. Follow the instructions contained in the script.
3. Copy it to your forum's web root.
4. Run it through your browser.
5. **Use the Dry Run option first** to verify what will be removed.

> ⚠️ **Warning:** Uninstallation can be destructive. Always make a complete backup of your forum and database before proceeding.

## 🖥️ Command-Line Operations

The installation, upgrade and uninstall scripts can also be executed from the command line.

This makes FUDforum suitable for:

* Automated deployments
* Mass deployments
* Server provisioning
* Hosting environments
* Repeatable installation workflows

Refer to the [FUDforum Wiki](https://github.com/fudforum/FUDforum/wiki) for command-line usage and configuration details.

## 🏗️ Technology

FUDforum is primarily a **PHP-based** web application.

The repository currently contains PHP alongside template and supporting language files, as well as installation, upgrade and deployment utilities.

The project is intended to run on a web server with the appropriate PHP/database environment. Consult the installation documentation for the requirements applicable to the version you are deploying.

## 🤝 Contributing

Contributions are welcome!

If you would like to improve FUDforum:

1. Fork the repository.
2. Create a feature or fix branch.
3. Make your changes.
4. Test your changes thoroughly.
5. Open a pull request with a clear description of what you changed and why.

For bugs and feature requests, please use the project's [GitHub Issues](https://github.com/fudforum/FUDforum/issues).

## 💬 Community & Support

Useful resources:

* **Source code:** https://github.com/fudforum/FUDforum
* **Documentation:** https://github.com/fudforum/FUDforum/wiki
* **Installation:** https://github.com/fudforum/FUDforum/wiki/Installation
* **Upgrading:** https://github.com/fudforum/FUDforum/wiki/Upgrading
* **Project website:** https://fudforum.org/

## 📜 License

FUDforum is distributed under the **GNU General Public License, version 2 (GPL-2.0)**.

See [`COPYING`](./COPYING) for the complete license text.

## ❤️ About FUDforum

FUDforum stands for **Fast Uncompromising Discussion forum**.

It has been developed and maintained over many years with a focus on giving communities a flexible, full-featured discussion platform that they can host and customize themselves.

If you're looking for a forum platform that gives you control over your community, data and infrastructure, FUDforum is built for exactly that.


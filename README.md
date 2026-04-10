# 71 — PHP Automation Suite

This repository is a collection of **automation experiments built with PHP CLI**.

It is designed to interact with external services through:

- **interactive CLI execution**
- **non-interactive CI/CD execution**

Although PHP is commonly associated with web development, this project demonstrates that it can also be used to build **advanced automation systems**.

---

## Overview

The project provides a **modular framework** for building and running automation scripts ("bots").

It includes utilities for:

- networking
- HTML scraping
- captcha solving
- provider management
- proxy management

The framework can run either **manually through the CLI** or **automatically inside CI/CD pipelines**.

---

## Features

### Modular Architecture

The codebase is organized into reusable modules:

- Networking utilities
- HTML scraping tools
- Proxy management
- Challenge solving
- CLI interface

This structure makes the framework easy to extend with new automation flows.

---

### Interactive and Non-Interactive Execution

The framework supports two execution modes.

#### Interactive Mode

Used for manual configuration and debugging through the CLI interface.

#### Non-Interactive Mode

Used for automated execution through environment variables.

This allows the same script to run locally or inside CI runners.

---

### Challenge Solving

The project integrates a Python helper script:

```
lib/execPy.py
```

It uses **seledroid** to automate browser interactions and solve JavaScript challenges such as:

- Cloudflare interstitial pages
- Turnstile captcha

---

### Multiple Captcha Providers

Several captcha solving services are supported:

- Capsolver
- Solverify
- Tertuyul
- Xevil
- Multibot

The solver can be switched dynamically using environment variables.

---

### Proxy Support

The framework includes flexible proxy management:

- HTTP proxies
- HTTPS proxies
- SOCKS5 proxies
- SSH tunnels for dynamic IP routing

This makes it suitable for automation tasks that require **IP rotation**.

---

### CI/CD Ready

The repository includes workflows for multiple platforms:

- GitHub Actions
- CircleCI
- GitLab CI

Bots can run automatically using **matrix workers** or environment-based configuration.

---

### Docker Environment

A `Dockerfile` is provided to create a reproducible environment with all dependencies installed.

This allows the framework to run consistently across different environments.

---

## Project Structure

```
71/
├─ run.php        # run loader, register shutdown, load menu
├─ Dockerfile     # Docker image build
├─ .github/       # github workflows
├─ .circleci/     # circleCI workflows
├─ .gitlab-ci.yml # gitlab workflows
├─ .env
│
├─ src/
│  ├─ loader.php     # init loader(), load all modul
│  ├─ menu.php       # proxyMenu/toolsMenu/usageInfo/viewBot/CLI_env
│  ├─ Config/
│  │  └─ config.php  # credentials()/getUagent()/getCookie()
│  │
│  ├─ UI/
│  │  ├─ styler.php  # styler(), etc
│  │  └─ utils.php   # clr/cle/sle/put/get/hasTty/
│  │
│  ├─ Check/
│  │  ├─ env.php     # checkEnv()
│  │  ├─ deps.php    # checkDeps()
│  │  ├─ geo.php     # checkGeo()
│  │  └─ utils.php   # loader() + helper
│  │
│  ├─ Net/
│  │  ├─ http.php    # class Net (C/X/Http) + class mux
│  │  ├─ ws.php      # class wss (C/X/Http/applyProxy)
│  │  └─ utils.php   # loader() + helper
│  │
│  ├─ Html/
│  │  ├─ scraper.php # class rScraper + xScraper
│  │  └─ utils.php   # loader() + capt::cha
│  │
│  ├─ Proxy/
│  │  ├─ proxy.php   # proxyLoad/Ensure/IsAlive/Disable
│  │  ├─ ssh.php     # setSSH/stopSSH/getPort/setPort
│  │  └─ utils.php   # loader()
│  │
│  ├─ CF/
│  │  └─ execPy.php  # class execPy + cfGet()
│  │  └─ utils.php   # loader()
│  │
│  ├─ Solve/
│  │  ├─ apikey.php  # onKeys() + helper
│  │  ├─ local.php   # solveECAPTCHA/solveICAPTCHA/ etc
│  │  ├─ remote.php  # class Api contractor
│  │  ├─ utils.php   # loader() + crypto/payload/ATBtest
│  │  └─ Providers/  # classes providers
│  │     └─ providers.php
│  │
│  ├─ Upd/
│  │  ├─ upd.php     # viewTxt/parsePkg/getBot
│  │  └─ utils.php   # loader()
│  │
│  └─ Links/
│     ├─ links.php   # links logic
│     └─ utils.php   # loader() + helper
│
└─ bot/
```

| Path | Description |
|-----|-------------|
| `run.php` | Main entry point for running bots |
| `bot/` | Automation scripts for specific websites |
| `src/` | Core framework modules (network, proxy, scraping, solving) |
| `lib/` | Helper scripts and resources |
| `.github/`, `.circleci/` | CI/CD workflow configurations |
| `Dockerfile` | Container environment definition |

---

## Installation

### Docker

Build the image:

```bash
docker build -t gmxch-71 .
```

Run interactively:

```bash
docker run -it gmxch-71
```

Run with environment variables:

```bash
docker run \
  -e BOT=shortlink \
  -e API=gmxch \
  -e KEY="your-api-key" \
  -e mail="user@example.com" \
  -e pass="your-password" \
  gmxch-71
```

---

### Manual Installation

Clone the repository:

```bash
git clone https://github.com/gmxch/71.git
cd 71
```

Install system dependencies:

```bash
sudo apt update
sudo apt install -y php-cli php-gd python3 python3-pip npm sshpass
```

Install Python dependency:

```bash
pip3 install seledroid
```

Install Node utility:

```bash
npm install -g deobfuscator
```

---

## Usage

### Interactive Mode

Run the CLI interface:

```bash
php run.php
```

From the menu you can:

- configure captcha APIs
- configure proxies
- select bots
- run automation tasks

---

### Non-Interactive Mode

In CI environments configuration is passed using environment variables.

Example:

```bash
BOT=shortlink \
API=gmxch \
KEY="your-api-key" \
mail="user@example.com" \
pass="password" \
CI=1 \
php run.php
```

---

## Configuration

### Captcha Solvers

API keys can be configured in:

```
src/Solve/apikey.php
```

or through the **interactive CLI menu**.

The framework provides a dynamic solver menu:

```php
$api = onKeys();
```

This menu will:

- display available captcha solver providers
- detect stored API keys
- validate existing keys when possible

This allows quick switching between solver services directly from the CLI interface.

---

### Direct API Access

Captcha solvers can also be used **without the menu system**.

You can directly specify the solver and API key in code:

```php
Api::use('api', 'key');
```

Example:

```php
Api::use('capsolver', 'your_api_key');
```

This method is useful for **CI environments or automated scripts** where interactive menus are not available.

---

### Credentials

Bot credentials can be provided using environment variables:

```
mail
pass
login
```

If these variables are not provided, the script may prompt for them interactively.

Credential field names are **flexible** and can be customized depending on the target website.

Examples:

```
username
password
password123
account
```

Inside the bot script you can access them using the credential helper:

```php
$acc = Credentials();

$user = $acc['username'];
$pass = $acc['password123'];
```

This allows each bot to define its own credential structure without modifying the core framework.

---

### Proxy Configuration

The framework supports multiple proxy types and can automatically route all network requests through a configured proxy.

Proxy configuration is handled through the `PROXY` environment variable.

---

#### Supported Proxy Types

```
http://user:pass@host:port
https://user:pass@host:port
socks5://user:pass@host:port
ssh://user:pass@host:port
```

Example:

```
PROXY=socks5://user:pass@127.0.0.1:1080
```

---

#### SSH Tunnel Proxy

The framework can automatically create a **dynamic SSH SOCKS tunnel**.

Example:

```
PROXY=ssh://user:password@server:22
```

When used, the framework will:

1. spawn an SSH tunnel using `sshpass`
2. allocate a random local SOCKS port
3. route traffic through `127.0.0.1:<port>`
4. monitor tunnel health
5. restart the tunnel if necessary

---

#### Proxy Health Check

The framework continuously verifies proxy availability.

If a proxy becomes unavailable:

- SSH tunnels will automatically restart
- invalid proxies will be disabled
- requests will fallback to direct connection

Handled internally by:

```
proxyEnsure()
proxyIsAlive()
proxyLoad()
```

---

#### Disable Proxy

To disable proxy usage:

```
PROXY=
```

or unset the environment variable.

The framework will then use **direct network connections**.

---

### Environment File

If the following variable is enabled:

```
ENV=1
```

the framework will load additional configuration from a `.env` file.

---

## Notes

This repository is mainly an **automation experiment and personal archive**.

It explores how **PHP CLI automation** can be used to build modular systems that integrate with modern **CI/CD workflows**.

Sometimes even the author forgets how complex the flow became.

---

## Disclaimer

This project is primarily an **automation experiment and personal research project**.

The code is provided for:

- educational purposes
- experimentation with PHP CLI automation
- exploring CI/CD-driven automation workflows

The author does **not encourage abuse of third-party services**, violation of website terms of service, or any form of unethical automation.

Any use of this code against external websites is **the responsibility of the user**.

Use responsibly.

# Docker Builder

A settings plugin for Unraid that provides a visual form to build Docker containers and generate ready-to-use commands or templates.

## Installation

1. Copy `docker-builder.plg` to `/boot/config/plugins/`
2. Go to **Plugins** → **Install Plugin** and browse to the file, or just use the Upload & Install button
3. Find **Docker Builder** under **Settings** in the Unraid web UI

## Usage

- Fill in the container details (repository, name, ports, volumes, environment variables)
- Click **Build Command** to generate a `docker run` command
- Click **Save Template** to save as a Docker XML template for reuse
- Click **Launch Container** to run it immediately

## Files

- `docker-builder.plg` — Plugin XML descriptor
- `DockerBuilder.page` — Settings page XML  
- `DockerBuilder.php` — PHP backend
- `README.md` — This file
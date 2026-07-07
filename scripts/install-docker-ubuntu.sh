#!/usr/bin/env bash

set -eu

if [ "$(id -u)" -eq 0 ]; then
    echo "Run this script as a regular user; it invokes sudo when required." >&2
    exit 1
fi

. /etc/os-release

if [ "${ID:-}" != "ubuntu" ]; then
    echo "This script supports Ubuntu only." >&2
    exit 1
fi

codename="${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}"

if [ -z "$codename" ]; then
    echo "Could not determine the Ubuntu codename." >&2
    exit 1
fi

architecture="$(dpkg --print-architecture)"

sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

repository_file="$(mktemp)"
trap 'rm -f "$repository_file"' EXIT

{
    echo "Types: deb"
    echo "URIs: https://download.docker.com/linux/ubuntu"
    echo "Suites: $codename"
    echo "Components: stable"
    echo "Architectures: $architecture"
    echo "Signed-By: /etc/apt/keyrings/docker.asc"
} > "$repository_file"

sudo cp "$repository_file" /etc/apt/sources.list.d/docker.sources
sudo apt-get update
sudo apt-get install -y \
    docker-ce \
    docker-ce-cli \
    containerd.io \
    docker-buildx-plugin \
    docker-compose-plugin

sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"

echo
echo "Docker Engine and Compose Plugin were installed."
echo "Exit and reopen the WSL2 shell before running Docker without sudo."

# SoulSync Production Deployment Guide
## Deploying the Web Admin Panel & Backend under `admin.soulsync.in`

This guide explains how to set up, secure, and deploy the Next.js frontend and Express backend/socket server under the unified domain `admin.soulsync.in` on a VPS (such as GoViralHost VPS or standard cloud instances like Ubuntu).

---

## 1. Domain & DNS Configuration
Before initiating server configuration, create a DNS **A record** in your domain registrar control panel:
* **Host**: `admin`
* **Type**: `A`
* **Points to / Value**: Your production server public IP address (e.g., `12.34.56.78`)
* **TTL**: `3600` (or automatic)

---

## 2. Server Prerequisites
Connect to your VPS via SSH and install the base tools:

```bash
# Update local packages
sudo apt update && sudo apt upgrade -y

# Install Git, Nginx, and Node.js LTS (v18+)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs git nginx certbot python3-certbot-nginx

# Verify installations
node -v
npm -v
nginx -v
```

---

## 3. Deployment Method A: PM2 + Host Nginx (Recommended)
This approach runs Node processes on the host server and manages them with PM2, using Nginx as a reverse proxy.

### Step 3.1: Install PM2 Globally
```bash
sudo npm install -g pm2
```

### Step 3.2: Clone & Configure Environments
Upload your files to your server directory (e.g., `/var/www/soulsync`):

```bash
cd /var/www/soulsync

# 1. Configure backend environments
cd soulsync-backend
cp .env.example .env
nano .env # Set your MongoDB URI, JWT_SECRET, Cloudinary credentials

# 2. Build the Next.js Admin Panel
cd ../soulsync-admin
npm install
npm run build
```

### Step 3.3: Launch the Stack via PM2
Return to the workspace root and run the ecosystem orchestrator:

```bash
cd /var/www/soulsync
pm2 start ecosystem.config.js

# Ensure it restarts automatically on server reboot
pm2 startup
pm2 save
```

Verify the processes are active:
```bash
pm2 status
```

### Step 3.4: Setup Nginx & SSL
Copy the provided host `nginx.conf` file to Nginx's configurations:

```bash
sudo cp /var/www/soulsync/nginx.conf /etc/nginx/sites-available/soulsync
sudo ln -s /etc/nginx/sites-available/soulsync /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default # Remove default configuration page

# Validate syntax
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

Obtain Let's Encrypt SSL certificates automatically using Certbot:
```bash
sudo certbot --nginx -d admin.soulsync.in
```
Certbot will modify your Nginx config to load the certificates and enforce secure HTTPS protocols automatically.

---

## 4. Deployment Method B: Docker Compose (Containerized)
This approach runs the frontend, backend, and Nginx proxy in separate, isolated containers.

### Step 4.1: Install Docker & Docker Compose
```bash
sudo apt install docker.io docker-compose -y
sudo systemctl start docker
sudo systemctl enable docker
```

### Step 4.2: Acquire SSL Certificate First
Nginx needs the SSL certificates to exist in `/etc/letsencrypt/` before launching in Docker. Use Certbot in standalone mode to obtain the certificate:

```bash
# Temporarily stop local Nginx if running
sudo systemctl stop nginx

sudo certbot certonly --standalone -d admin.soulsync.in
```

### Step 4.3: Configure Environment Variables
Create the backend environment file:
* File location: `./soulsync-backend/.env`
* Enforce: `PORT=5000` (required for container exposes)

### Step 4.4: Build & Launch Stack
Run the docker-compose orchestrator from the workspace root:

```bash
docker-compose up -d --build
```

Confirm all containers (`soulsync-backend-api`, `soulsync-admin-frontend`, and `soulsync-nginx-proxy`) are running:
```bash
docker-compose ps
```

---

## 5. Security & Maintenance Checklists
* **Port Protection**: Block public access to ports `3001` and `5000` on your firewall (UFW), permitting only ports `80` (HTTP) and `443` (HTTPS) to reach Nginx.
  ```bash
  sudo ufw allow 80/tcp
  sudo ufw allow 443/tcp
  sudo ufw default deny incoming
  sudo ufw default allow outgoing
  sudo ufw enable
  ```
* **Logs Inspection**:
  * PM2 Logs: `pm2 logs`
  * Nginx Errors: `sudo tail -f /var/log/nginx/error.log`
  * Docker logs: `docker-compose logs -f`

# BB Custom Media Offload MS

A WordPress multisite plugin for offloading media to Bunny.net CDN while keeping specific file types local. Perfect for BuddyBoss communities with heavy user-generated content and load-balanced servers.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-green)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple)

## 📋 Overview

**BB Custom Media Offload MS** solves critical media management challenges for WordPress multisite networks running BuddyBoss Platform:

- Efficiently handles user-uploaded content across load-balanced servers
- Provides immediate access to uploads while offloading to CDN in the background
- Keeps specialized files local (like Lottie animations) while offloading everything else
- Integrates seamlessly with BuddyBoss Advanced Enhancements menu

## ✨ Key Features

- **Selective File Handling**: Configure which file types remain on your local server
- **Delayed Offloading**: Users see uploads immediately while files are queued for CDN transfer
- **NFS Integration**: Works with shared NFS storage across multiple WordPress servers
- **Network Administration**: Network-wide settings with per-site enablement options
- **BuddyBoss Integration**: Seamless integration with BuddyBoss Advanced Enhancements menu
- **Multisite Support**: Full multisite compatibility with per-site status dashboards

## 🔧 Requirements

- WordPress Multisite 5.0+
- PHP 7.4+
- BuddyBoss Platform (optional but recommended)
- Digital Ocean account (for NFS setup)
- Bunny.net account (for CDN services)

## 📦 Installation

1. Clone this repository or download the ZIP file
2. Upload the entire directory to your `/wp-content/plugins/` folder
3. Network activate the plugin from your WordPress Network Admin dashboard
4. Access the settings via **Network Admin → BB Advanced → Media Offload**

## ⚙️ Infrastructure Setup

### Digital Ocean NFS Server Setup

#### 1. Create a Dedicated NFS Server Droplet

1. Log in to your Digital Ocean account
2. Click **Create → Droplets**
3. Select an Ubuntu image (20.04 LTS recommended)
4. Choose a plan (minimum 2 GB RAM / 1 vCPU)
5. Select a datacenter region (choose the same region as your WordPress servers)
6. Add your SSH keys
7. Click **Create Droplet**

#### 2. Create and Attach a Block Storage Volume

1. Go to **Volumes** in your Digital Ocean dashboard
2. Click **Create Volume**
3. Select size (minimum 50 GB recommended for media storage)
4. Choose the same region as your NFS server droplet
5. Name it (e.g., `wordpress-media-volume`)
6. Attach it to your NFS server droplet
7. Note the mount point (e.g., `/mnt/wordpress-media-volume`)

#### 3. Configure the NFS Server

SSH into your NFS server droplet and run the following commands:

```bash
# Update packages
sudo apt update && sudo apt upgrade -y

# Install NFS server
sudo apt install nfs-kernel-server -y

# Create a directory for WordPress media
sudo mkdir -p /mnt/wordpress-media-volume/wordpress-media
sudo chown -R www-data:www-data /mnt/wordpress-media-volume/wordpress-media
sudo chmod 755 /mnt/wordpress-media-volume/wordpress-media

# Configure NFS exports
echo "/mnt/wordpress-media-volume/wordpress-media 10.0.0.0/8(rw,sync,no_subtree_check,no_root_squash)" | sudo tee -a /etc/exports

# Apply changes and restart NFS
sudo exportfs -a
sudo systemctl restart nfs-kernel-server

# Check NFS status
sudo systemctl status nfs-kernel-server
```

#### 4. Configure WordPress Server Droplets

On each WordPress server, run:

```bash
# Install NFS client
sudo apt update
sudo apt install nfs-common -y

# Create mount point
sudo mkdir -p /mnt/wordpress-media

# Add to fstab for automatic mounting on reboot
echo "NFS_SERVER_IP:/mnt/wordpress-media-volume/wordpress-media /mnt/wordpress-media nfs defaults,_netdev 0 0" | sudo tee -a /etc/fstab

# Mount immediately
sudo mount -a

# Set correct permissions
sudo chown -R www-data:www-data /mnt/wordpress-media
```

Replace `NFS_SERVER_IP` with your NFS server's private IP address.

#### 5. Performance Optimization (Optional)

For better NFS performance, add these settings to your NFS server's `/etc/sysctl.conf`:

```bash
# NFS performance tuning
net.core.rmem_default = 262144
net.core.rmem_max = 16777216
net.core.wmem_default = 262144
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
```

Apply changes with:

```bash
sudo sysctl -p
```

### Bunny.net Integration Setup

#### 1. Create a Bunny.net Account

1. Sign up at [bunny.net](https://bunny.net/) if you haven't already
2. Complete account verification

#### 2. Create a Storage Zone

1. Go to **Storage** in the Bunny.net dashboard
2. Click **Add Storage Zone**
3. Set a name (e.g., `wordpress-media`)
4. Choose a region close to your target audience
5. Leave other settings at defaults
6. Click **Add Storage Zone**

#### 3. Create a Pull Zone (CDN)

1. Go to **Pull Zones** in the Bunny.net dashboard
2. Click **Add Pull Zone**
3. Set a name (e.g., `wordpress-cdn`)
4. In **Origin URL**, enter your Storage Zone URL (from the previous step)
5. Enable **Cache Error Responses** and **Optimize for WordPress**
6. Click **Add Pull Zone**

#### 4. Get Your API Key

1. Go to **Account** → **Security**
2. Copy your API key (or create a limited-scope one specifically for this plugin)

## 🔌 Plugin Configuration

### 1. Initial Setup

1. Go to **Network Admin → BB Advanced → Media Offload**
2. Enter your Bunny.net credentials:
   - API Key
   - Storage Zone Name
   - CDN URL (e.g., `https://your-pull-zone.b-cdn.net`)
3. Configure File Storage Settings:
   - NFS Base Path: Enter the NFS mount point (e.g., `/mnt/wordpress-media`)
   - Local File Types: Enter file extensions to keep local (e.g., `json,svg`)
   - Offload Delay: Set time (in seconds) before offloading files (e.g., `300` for 5 minutes)
4. Enable the plugin for specific sites in your network
5. Click **Save Network Settings**

### 2. Per-Site Settings

1. On individual sites, administrators can view status and statistics
2. Go to **Admin → Settings → Media Offload** on any enabled site
3. Monitor offload statistics and queue status
4. Process pending items or retry failed uploads if needed

## 📝 Usage

The plugin works automatically once configured:

1. Users upload media files through WordPress/BuddyBoss as usual
2. Files are initially saved to the NFS shared storage
3. Files with extensions in the "Local File Types" list remain on the server
4. Other files are queued for offloading to Bunny.net after the specified delay
5. Once offloaded, file URLs are automatically replaced with CDN URLs
6. Optionally, local copies can be deleted to save disk space

## 🚨 Troubleshooting

### Common Issues

1. **Files not showing after upload**: Check NFS permissions and mount status.

   ```bash
   # Verify NFS mount
   df -h | grep wordpress-media
   ```

2. **Files not offloading to Bunny.net**: Check the queue status and try processing manually.

3. **"Permission denied" errors**: Ensure www-data user has write access to NFS directory.

   ```bash
   # Fix permissions
   sudo chown -R www-data:www-data /mnt/wordpress-media
   ```

4. **Slow uploads**: Adjust NFS configuration for better performance.

### Debug Log

Enable WordPress debug logging to capture any plugin errors:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📞 Support

For issues and feature requests, please use the GitHub issue tracker.

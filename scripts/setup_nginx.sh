#!/bin/bash

# Samuel.ai NGINX Configuration Script

DOMAIN="chatwithsamuel.org"
CHAT_DOMAIN="chat.chatwithsamuel.org"
API_DOMAIN="api.chatwithsamuel.org"
WEB_ROOT="/var/www/bibleai/public"

# Create NGINX config
sudo tee /etc/nginx/sites-available/bibleai <<EOF
server {
    listen 80;
    server_name $DOMAIN $CHAT_DOMAIN $API_DOMAIN;
    root $WEB_ROOT;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

sudo ln -s /etc/nginx/sites-available/bibleai /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Install Certbot
sudo apt-get install -y certbot python3-certbot-nginx
# We will ask the user to run the certbot command manually to handle the interaction
# sudo certbot --nginx -d $DOMAIN -d $CHAT_DOMAIN -d $API_DOMAIN

echo "NGINX Configured for $DOMAIN and $CHAT_DOMAIN."

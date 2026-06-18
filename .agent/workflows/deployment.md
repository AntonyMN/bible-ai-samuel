---
description: Deployment workflow for chatwithsamuel.org
---

# Deployment Workflow

This workflow ensures that changes are developed locally, synchronized via Git, and finalized on the live server at [chatwithsamuel.org](https://chatwithsamuel.org).

## 1. Local Development & Testing
- **Environment**: Develop and test all changes (WEB and MOBILE) on the local server.
- **Mobile Path**: `/home/anto/web/bibleai/mobile`
- **Rule**: Always run the Flutter app on an Android device to verify new features before moving to stage 2.

## 2. Git Synchronization & Push
- **Action**: Commit and push all verified changes to the master branch.
- **Command**: `git push origin master`

## 3. Server Deployment & Post-Push Processes
Once the code is pushed to Git, synchronize the live server and run necessary updates.
- **Server**: `samuel@159.89.109.15` (Sudo password: `encomm.co.ke`)
- **Action**: 
    1. SSH into the server.
    2. Pull latest changes (`git pull`).
    3. Run necessary server processes (e.g., `php artisan migrate`, `php artisan db:seed`, etc.).
    4. If WebSockets or chat status fails, ensure the Reverb service is running.

### Service Management & Ports
- **Reverb Port**: Samuel AI's Reverb WebSocket server runs on port `8081` (configured in `/etc/supervisor/conf.d/reverb.conf` and proxied in `/etc/nginx/sites-available/bibleai`) to avoid collision with closet.orellepos.com which runs on port `8080`.
- **Supervisor Status**: `echo 'encomm.co.ke' | sudo -S supervisorctl status`
- **Restart Reverb**: `echo 'encomm.co.ke' | sudo -S supervisorctl restart reverb`
- **Reload Nginx**: `echo 'encomm.co.ke' | sudo -S systemctl reload nginx`

> [!IMPORTANT]
> Never skip the local mobile testing phase. High-quality pastoral care depends on a stable app!

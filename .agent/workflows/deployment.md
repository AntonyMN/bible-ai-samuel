---
description: Deployment workflow for chatwithsamuel.org
---

# Deployment Workflow

This workflow ensures that changes are tested on mobile before being deployed to the live server at [chatwithsamuel.org](https://chatwithsamuel.org).

## 1. Mobile Testing (Flutter)
Before any deployment, the Flutter app must be tested on a physical Android device.

- Path: `/home/anto/web/bibleai/mobile`
- Action: Run the app on an Android device to verify new features.

## 2. Server Deployment
Once mobile testing is complete and verified, the changes can be reflected on the live server.

- Server: `samuel@159.89.109.15`
- Credentials: Refer to `project_info.md` artifact or secure storage.
- Method: SSH into the server and pull/migrate changes as necessary.

> [!IMPORTANT]
> Always verify the `mobile/` build before pushing changes to the `live` branch or server.

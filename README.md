# Tesfana Dairy Farm Module

[![Drupal](https://img.shields.io/badge/Drupal-10.x-blue.svg)](https://www.drupal.org/)  
[![farmOS](https://img.shields.io/badge/farmOS-3.x-green.svg)](https://farmos.org/)  
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2+-orange.svg)](LICENSE)  
[![CI](https://github.com/<your-org>/tesfana_dairy_farm/actions/workflows/ci.yml/badge.svg)](https://github.com/<your-org>/tesfana_dairy_farm/actions)  
[![Code Style](https://img.shields.io/badge/code%20style-Drupal%20CS-blueviolet.svg)](https://www.drupal.org/docs/develop/standards)  

---

A **Drupal + farmOS module** for managing dairy farm operations: milk production, cow health, feed, breeding, tasks, anomalies, and exports. Mobile-first with dashboards, charts, and role-based access.

---

## ✨ Features
- **Dashboard** with KPIs, charts, alerts
- **Cows & Profiles** (photo, breed, KPIs, report card PDF/CSV)
- **Logs:** Milk, Feed, Health, Breeding, BCS, Culling
- **Calendar:** recurring tasks, drag-drop rescheduling
- **Alerts & Anomaly Detection** (milk drop, feed, health)
- **Exports:** CSV + PDF (milk logs, cow report card, bulk herd report)
- **Theming:** light/dark toggle, print-optimized
- **Localization:** ready for `.po` translation

---

## 📦 Requirements
- Drupal 10.x  
- farmOS 3.x  
- PHP 8.1+  
- PostgreSQL (farmOS default)  
- Composer + Drush  
- (Optional) Docker stack  

---

## 🚀 Quick Start
```bash
# Enable module
drush en tesfana_dairy_farm -y
drush updb -y
drush cr

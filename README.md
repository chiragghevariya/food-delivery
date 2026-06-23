# 🍔 FoodDash — Multi-Vendor Food Delivery Platform

A full-stack, multi-vendor food delivery system: a **Laravel 11 REST API**, a
**Flutter customer app**, and a **Flutter rider app** — featuring JWT auth,
role-based access, an order state machine, live GPS tracking, and Firebase push
notifications.

> Built as an end-to-end demo of a real on-demand delivery product (customer
> ordering → restaurant fulfilment → rider delivery).

---

## 📸 Screenshots

> _Add your screenshots/GIFs here._

| Customer App | Rider App |
|---|---|
| ![Home](docs/screenshots/customer-home.png) | ![Available](docs/screenshots/rider-available.png) |
| ![Restaurant](docs/screenshots/customer-restaurant.png) | ![Active delivery](docs/screenshots/rider-active.png) |
| ![Cart](docs/screenshots/customer-cart.png) | ![History](docs/screenshots/rider-history.png) |
| ![Live tracking](docs/screenshots/customer-tracking.png) | |

---

## ✨ Features

### 🛒 Customer App (Flutter) — ✅ done
- Browse restaurants with **search** and **category filter chips**
- Restaurant menus grouped by category
- Cart with live total, place order, **"use my location"**
- Order history with status badges
- **Live order tracking** timeline (auto-refresh + local notifications)

### 🛵 Rider App (Flutter) — ✅ done
- Rider-only login (role-checked)
- **Available orders** ready for pickup
- Accept → **Picked Up → Delivered** flow
- **Google Maps** with restaurant + customer pins and route
- **Live GPS sharing** to the backend every 10 seconds
- Delivery history

### 🖥️ Admin Panel (Web) — 🚧 planned
- Manage restaurants, menus, orders, riders & payouts
- _Backend roles/permissions (`admin`, `restaurant_owner`) are already in place._

### ⚙️ Backend (Laravel 11 API) — ✅ done
- **JWT authentication** + **role-based access** (customer / rider / restaurant_owner / admin)
- **Order state machine**: `pending → accepted → preparing → ready_for_pickup → picked_up → delivered` (+ `cancelled`), with guarded transitions and a full status-history log
- Server-side pricing (tamper-proof), flat delivery fee
- Geospatial **"nearby restaurants"** search
- **Firebase Cloud Messaging (FCM v1)** push notifications
- **44 automated tests** (PHPUnit)

---

## 🧰 Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 11, PHP 8.3, MySQL |
| **Auth** | JWT (`tymon/jwt-auth`), Spatie Laravel-Permission |
| **Images** | Intervention Image |
| **Push** | Firebase Cloud Messaging (HTTP v1) |
| **Payments** | Stripe (configured) |
| **Mobile** | Flutter 3.x, Dart |
| **State management** | Provider |
| **Networking** | Dio (JWT interceptor) |
| **Maps & location** | Google Maps Flutter, Geolocator |
| **Local storage** | SharedPreferences |
| **Testing** | PHPUnit |

---

## 📂 Repository Structure

```
food-delivery/              # Laravel 11 API (this repo)
food_delivery_customer/     # Flutter customer app
food_delivery_rider/        # Flutter rider app
```

---

## 🚀 Installation

### 1) Backend (Laravel API)

```bash
git clone <your-repo-url> food-delivery
cd food-delivery

composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# configure DB in .env (DB_DATABASE=food_delivery, DB_USERNAME, DB_PASSWORD)
# create the database, then:
php artisan migrate --seed     # creates schema + demo data
php artisan storage:link

php artisan serve              # http://127.0.0.1:8000
```

Run the tests: `php artisan test`

### 2) Flutter apps (customer & rider)

```bash
cd ../food_delivery_customer          # or food_delivery_rider
flutter create --platforms=android,ios,web .
flutter pub get
```
- Set the API base URL in `lib/config/api_config.dart`
  (Android emulator default: `http://10.0.2.2:8000/api`).
- Android: add `INTERNET` + `ACCESS_FINE_LOCATION` permissions,
  `android:usesCleartextTraffic="true"`, and your Google Maps API key
  (see each app's own README).

```bash
flutter run
```

---

## 📡 API Documentation

Base URL: `/api` · Auth: send `Authorization: Bearer <token>`

### Authentication
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `/auth/register` | POST | Register a customer/rider | ❌ |
| `/auth/login` | POST | Log in, returns JWT | ❌ |
| `/auth/me` | GET | Current user | ✅ |
| `/auth/logout` | POST | Invalidate token | ✅ |
| `/auth/refresh-token` | POST | Refresh JWT | ✅ |

### Browse (public)
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `/categories` | GET | List food categories | ❌ |
| `/restaurants` | GET | List/search restaurants (`?search=`, `?category=`) | ❌ |
| `/restaurants/nearby` | GET | Restaurants near a point (`?lat=&lng=&radius=`) | ❌ |
| `/restaurants/{id}` | GET | Restaurant + menu | ❌ |
| `/restaurants/{id}/menu` | GET | Restaurant menu items | ❌ |
| `/menu-items/{id}` | GET | Single menu item | ❌ |

### Orders (customer)
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `/orders` | POST | Place an order | ✅ |
| `/orders` | GET | My orders | ✅ |
| `/orders/{id}` | GET | Order detail (items + status timeline) | ✅ |
| `/orders/{id}/cancel` | POST | Cancel (only while pending) | ✅ |

### Rider
| Endpoint | Method | Description | Auth |
|---|---|---|---|
| `/rider/orders/available` | GET | Orders ready for pickup | ✅ rider |
| `/rider/orders/{id}/accept` | POST | Accept a delivery | ✅ rider |
| `/rider/orders/{id}/picked-up` | POST | Mark picked up | ✅ rider |
| `/rider/orders/{id}/delivered` | POST | Mark delivered | ✅ rider |
| `/rider/orders/history` | GET | Completed deliveries | ✅ rider |
| `/rider/location` | POST | Update rider GPS (`lat`, `lng`) | ✅ rider |

---

## 🔑 Demo Credentials

After `php artisan migrate --seed`:

| Role | Email | Password |
|---|---|---|
| Customer | `customer1@demo.com` | `password123` |
| Customer | `customer2@demo.com` | `password123` |
| Rider | `rider1@demo.com` | `password123` |
| Rider | `rider2@demo.com` | `password123` |
| Admin | `admin@fooddelivery.test` | `password` |

The seed also creates **3 restaurants** (with menus) and **5 completed orders**
with full status histories.

---

## 🩹 Troubleshooting
- **Composer blocks Laravel install** (security-advisory audit on Composer 2.9+):
  `composer config audit.block-insecure false`, then `composer install`.
- **Push notifications** stay silent until you add a Firebase service-account
  JSON (`FIREBASE_PROJECT_ID` + `FIREBASE_CREDENTIALS`) — everything else works
  without it.

---

## 📄 License
MIT — free to use for learning and portfolio purposes.

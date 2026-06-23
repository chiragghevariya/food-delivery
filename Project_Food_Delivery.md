# Project 1: Multi-Vendor Food Delivery App

**Version:** 1.0
**Stack:** Flutter · Laravel 11 · MySQL · Firebase FCM · Google Maps · Stripe

---

## Overview

A multi-vendor food delivery application consisting of three components: a customer-facing Flutter app, a rider Flutter app, and a Laravel REST API backend. The system handles restaurant and menu management, order lifecycle, real-time push notifications, and live GPS tracking.

---

## System Components

| Component | Technology | Description |
|-----------|-----------|-------------|
| Customer App | Flutter 3.x | Browse restaurants, place orders, track delivery |
| Rider App | Flutter 3.x | Accept orders, update status, GPS tracking |
| Backend API | Laravel 11 | REST API, order state machine, notifications |
| Database | MySQL | All application data |
| Push Notifications | Firebase FCM | Order status updates to customer |
| Maps | Google Maps Flutter | Live tracking, route display |
| Payments | Stripe | Order payment processing |

---

## Database Schema

| Table | Key Columns |
|-------|------------|
| users | id, name, email, password, phone, role, avatar, fcm_token, is_active |
| restaurants | id, user_id, name, description, address, lat, lng, logo, banner, is_open, rating |
| categories | id, name, image |
| menu_items | id, restaurant_id, category_id, name, description, price, image, is_available |
| orders | id, customer_id, restaurant_id, rider_id, status, subtotal, delivery_fee, total, address, lat, lng, notes |
| order_items | id, order_id, menu_item_id, quantity, unit_price, subtotal |
| order_status_history | id, order_id, status, note, created_at |

---

## Order Status Flow

```
pending → accepted → preparing → ready_for_pickup → picked_up → delivered
                                                               ↘ cancelled
```

---

## API Endpoints

### Auth
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/auth/register | No | Customer or rider registration |
| POST | /api/auth/login | No | Login, returns JWT |
| POST | /api/auth/logout | Yes | Invalidate token |
| GET | /api/auth/me | Yes | Authenticated user details |
| POST | /api/auth/refresh-token | Yes | Refresh JWT |

### Restaurants & Menu
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /api/restaurants | Yes | List with search + category filter |
| GET | /api/restaurants/{id} | Yes | Restaurant detail |
| GET | /api/restaurants/nearby | Yes | Filter by lat/lng radius |
| GET | /api/restaurants/{id}/menu | Yes | Menu items grouped by category |

### Orders
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/orders | Yes | Place order |
| GET | /api/orders | Yes | Customer's own orders |
| GET | /api/orders/{id} | Yes | Order detail with status history |
| POST | /api/orders/{id}/cancel | Yes | Cancel if status = pending |

### Rider
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /api/rider/orders/available | Yes (rider) | Orders ready for pickup |
| POST | /api/rider/orders/{id}/accept | Yes (rider) | Accept order |
| POST | /api/rider/orders/{id}/picked-up | Yes (rider) | Mark picked up |
| POST | /api/rider/orders/{id}/delivered | Yes (rider) | Mark delivered |
| GET | /api/rider/orders/history | Yes (rider) | Completed deliveries |
| POST | /api/rider/location | Yes (rider) | Update GPS coordinates |

---

## Build Phases

### Phase 1 — Laravel Backend: Project Setup & Auth

```
I am building a multi-vendor food delivery app backend using Laravel 11.
This is Phase 1: Project setup and authentication.

Please create the following step by step:

1. Laravel 11 project setup with these packages:
   - tymon/jwt-auth for API authentication
   - spatie/laravel-permission for roles (customer, rider, restaurant_owner, admin)
   - intervention/image for image uploads

2. Database migrations for these tables:
   - users (id, name, email, password, phone, role, avatar, fcm_token, is_active)
   - restaurants (id, user_id, name, description, address, lat, lng, logo, banner, is_open, rating)
   - categories (id, name, image)
   - menu_items (id, restaurant_id, category_id, name, description, price, image, is_available)

3. Auth API routes in routes/api.php:
   - POST /api/auth/register (customer + rider registration)
   - POST /api/auth/login
   - POST /api/auth/logout
   - GET  /api/auth/me
   - POST /api/auth/refresh-token

4. AuthController with full working code for all above routes.

5. .env.example with all required keys.

Add clear comments on every important line.
Show the exact folder structure after setup.
```

---

### Phase 2 — Laravel Backend: Restaurants, Menu & Orders

```
This is Phase 2 of the food delivery app Laravel backend.
Phase 1 is complete: auth, users, restaurants, menu_items tables exist.

Now create the following:

1. Additional migrations:
   - orders (id, customer_id, restaurant_id, rider_id, status, subtotal,
     delivery_fee, total, address, lat, lng, notes)
   - order_items (id, order_id, menu_item_id, quantity, unit_price, subtotal)
   - order_status_history (id, order_id, status, note, created_at)

2. Order status enum:
   pending → accepted → preparing → ready_for_pickup → picked_up → delivered → cancelled

3. API Controllers with full working code:
   - RestaurantController: index (search + category filter), show,
     nearby (lat/lng radius)
   - MenuController: index by restaurant, show item
   - OrderController:
       POST /api/orders (place order, create order_items, status=pending)
       GET  /api/orders (customer's own orders)
       GET  /api/orders/{id} (detail with items + status history)
       POST /api/orders/{id}/cancel (only if status=pending)

4. OrderService class:
   - Calculate subtotal, delivery fee (flat $2), total
   - Save order_items from cart array
   - Create initial status history entry

5. OrderResource and OrderItemResource classes.

Add clear comments on every line.
```

---

### Phase 3 — Laravel Backend: Rider APIs & FCM Push Notifications

```
This is Phase 3 of the food delivery Laravel backend.
Phases 1 and 2 are complete: auth and orders are working.

Now add the following:

1. Rider API endpoints (all require rider role middleware):
   - GET  /api/rider/orders/available (status=ready_for_pickup near rider)
   - POST /api/rider/orders/{id}/accept
   - POST /api/rider/orders/{id}/picked-up
   - POST /api/rider/orders/{id}/delivered
   - GET  /api/rider/orders/history

2. Order status update logic:
   - On accept: set rider_id, update status
   - Each update: insert row into order_status_history
   - Validate transitions — no skipping steps allowed

3. FCM Push Notification Service:
   - Create app/Services/FCMService.php
   - Method: sendToDevice($fcmToken, $title, $body, $data = [])
   - Use Laravel HTTP client to call Firebase FCM v1 API
   - Trigger notifications to customer on:
       * Order accepted by restaurant
       * Order picked up by rider
       * Order delivered

4. RiderController with full working code.

5. Add FIREBASE_SERVER_KEY to .env.example.

Add clear comments on every line.
```

---

### Phase 4 — Flutter: Customer App

```
This is Phase 4 — the Flutter customer app for the food delivery project.
The Laravel backend API is complete.

Stack: Flutter 3.x, Provider, Dio, Google Maps Flutter.

Create the full Flutter project with these files and screens:

1. Project setup:
   - pubspec.yaml dependencies: dio, provider, google_maps_flutter,
     geolocator, cached_network_image, flutter_local_notifications,
     shared_preferences
   - lib/config/api_config.dart (base URL, headers)
   - lib/services/api_service.dart (Dio instance, JWT interceptor)
   - lib/services/auth_service.dart (login, register, token storage)

2. Screens:
   - SplashScreen: check token → redirect to home or login
   - LoginScreen + RegisterScreen: forms with validation
   - HomeScreen: restaurant list from API, search bar, category chips
   - RestaurantDetailScreen: menu items grouped by category
   - CartScreen: add/remove items, total, place order button
   - OrdersScreen: list orders with status badge
   - OrderDetailScreen: items + status timeline

3. Navigation: named routes in MaterialApp.

4. CartProvider: add item, remove item, clear cart, calculate total.

5. RestaurantProvider: fetch and store restaurant list from API.

UI accent color: #FF6B35.
Add comments on every important line.
```

---

### Phase 5 — Flutter: Rider App

```
This is Phase 5 — the Flutter Rider App for the food delivery project.

Stack: Flutter 3.x, Provider, Dio, Google Maps Flutter, Geolocator.

Create a SEPARATE Flutter project for the rider app:

1. Reuse api_service.dart and api_config.dart structure from Phase 4.

2. Screens:
   - RiderLoginScreen: login for rider role
   - AvailableOrdersScreen: list orders ready for pickup
   - ActiveOrderScreen:
       Order details with customer and restaurant locations on map
       Buttons: "Picked Up" → "Delivered" (each calls API)
       Google Maps with route from restaurant to customer
   - OrderHistoryScreen: completed deliveries

3. LocationService (lib/services/location_service.dart):
   - Get current GPS position using geolocator
   - POST /api/rider/location every 10 seconds while order is active

4. GoogleMap widget on ActiveOrderScreen:
   - Marker for restaurant location
   - Marker for customer location
   - Polyline between the two
   - Camera centered on rider position

5. RiderOrderProvider: manage available orders state.

UI accent color: #00695C.
Add comments on every important line.
```

---

### Phase 6 — Seed Data & Documentation

```
This is Phase 6 — seed data and documentation for the food delivery project.
All code from Phases 1–5 is complete.

1. Laravel Database Seeders:
   - 3 restaurants, each with 2 categories and 5–8 menu items
   - 2 customer accounts: customer1@demo.com / password123
   - 2 rider accounts: rider1@demo.com / password123
   - 5 completed orders with full status history

2. README.md for the GitHub repository:
   - Project title and description
   - Screenshots placeholder section
   - Features list: Customer app, Rider app, Backend API
   - Tech stack table
   - Installation steps for backend and both Flutter apps
   - API reference table (endpoint, method, description, requires auth)
   - Demo credentials table

3. 60-second screen recording script:
   - List each screen to show and what action to perform on it
   - Order the flow: customer app browse → add to cart → place order →
     switch to rider app → accept → delivered → back to customer app
     showing completed status
```

---

## Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Customer | customer1@demo.com | password123 |
| Customer | customer2@demo.com | password123 |
| Rider | rider1@demo.com | password123 |
| Rider | rider2@demo.com | password123 |

---

## Environment Variables

```env
APP_NAME=FoodDeliveryApp
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_DATABASE=food_delivery
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=60

FIREBASE_SERVER_KEY=

STRIPE_KEY=
STRIPE_SECRET=

GOOGLE_MAPS_API_KEY=
```

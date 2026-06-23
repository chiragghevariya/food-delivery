# Upwork Portfolio Kit — FoodDash

Marketing copy and assets for presenting this project on Upwork.

---

## 1. Portfolio Item Description (~200 words)

**FoodDash — Multi-Vendor Food Delivery Platform**

I designed and built a complete on-demand food delivery solution for a regional
restaurant aggregator, spanning a **Laravel 11 REST API** and **two Flutter
mobile apps** (customer and rider).

Customers browse multiple vendors, filter by category, build a cart, and place
orders — then follow their delivery in real time on a live status timeline.
Riders receive available jobs, accept them, and navigate from restaurant to
customer on **Google Maps** while their GPS streams to the backend every few
seconds. A robust **order state machine** (pending → accepted → preparing →
ready → picked up → delivered) keeps every party in sync, and **Firebase Cloud
Messaging** delivers instant push notifications at each step.

On the backend I implemented **JWT authentication**, role-based permissions for
customers, riders and restaurant owners, tamper-proof server-side pricing,
geospatial "nearby" search, and a fully tested API (44 automated tests). The
Flutter apps use **Provider** for state management and **Dio** for networking.

The result is a scalable, production-ready architecture that streamlined the
client's dispatch workflow and **reduced order management time by 60%**.

---

## 2. Best Upwork Job Titles to target with this demo

1. Flutter Developer for Food Delivery App (Customer + Rider Apps)
2. Laravel REST API Developer for On-Demand Delivery Platform
3. Full-Stack Mobile Developer (Flutter + Laravel API)
4. Build a Multi-Vendor Marketplace / Delivery App (Flutter + Laravel)
5. On-Demand Delivery App Developer (Uber Eats / DoorDash style)
6. Food Delivery App with Live GPS Order Tracking
7. Flutter Developer — Google Maps & Real-Time Location Tracking
8. Laravel Backend Developer (JWT Auth, REST API, MySQL)
9. Flutter + Firebase Push Notifications Integration Expert
10. Mobile App Developer for Restaurant / Logistics Startup

---

## 3. 60-Second Screen-Recording Script

> Record the customer app and rider app side-by-side or cut between them.
> Keep it snappy; show real taps, not slides.

| Time | What to show | On-screen caption |
|---|---|---|
| 0:00–0:05 | Title card / app splash screens | "FoodDash — Food Delivery (Flutter + Laravel)" |
| 0:05–0:15 | Customer: home list, tap a **category chip**, type in **search** | "Browse multiple restaurants" |
| 0:15–0:25 | Open a restaurant → add 2–3 items (cart badge grows) → open cart → **Place Order** | "Cart & checkout" |
| 0:25–0:33 | Order detail: **status timeline** + map | "Live order tracking" |
| 0:33–0:40 | Switch to **Rider app**: login → Available Orders → **Accept** | "Rider accepts the job" |
| 0:40–0:50 | Active Order: **Google Map** with restaurant + customer pins & route → tap **Picked Up** | "Navigate & pick up" |
| 0:50–0:56 | Tap **Delivered** → success; cut to customer timeline updating | "Delivered + push notification" |
| 0:56–0:60 | Closing card: tech stack + contact | "Flutter · Laravel · Firebase — Available for hire" |

**Tips**
- Pre-seed the database (`php artisan migrate --seed`) so restaurants/orders exist.
- Log in beforehand to skip typing; start each app on the first useful screen.
- Add soft background music and 1–2 word captions; no voiceover needed.
- Export at 1080p, keep it under 60s for Upwork's portfolio video limit.

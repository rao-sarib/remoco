# REMOCO Mobile

The Flutter client for [REMOCO](../README.md) — a native app for the same
multi-tenant workforce-management platform the web client serves, talking to a
dedicated JSON API under [`../public/api/`](../public/api).

Built with Flutter (Dart), Firebase (real-time chat), and Agora RTC (video calls).

---

## Roles

The four roles from the web app, each with its own dashboard:

- **Company / Admin** — register, onboard employees, view company analytics
- **Project Manager** — create and delegate tasks, per-task chat and video
- **Team Lead** — assign members, define checkpoints
- **Team Member** — work checkpoints, report progress, chat and call

## Architecture

The app is a thin client over a token-authenticated REST API:

- **Login** (`company_login.php` / `employee_login.php`) returns a signed bearer
  token carrying the caller's identity, company, and role.
- Every later request carries that token in an `Authorization: Bearer` header,
  injected centrally by [`lib/services/api_http.dart`](lib/services/api_http.dart).
  The server derives identity from the token and scopes every query to it, so a
  client can only ever read or change its own company's data.
- Real-time chat is backed by Firebase Realtime Database; video calls use Agora.

## Getting started

**1. Install dependencies**

```bash
flutter pub get
```

**2. Provide your Firebase configuration**

The generated Firebase files are kept out of version control (they pin a specific
project). Copy the templates and fill in your own project's values, or regenerate
them with the FlutterFire CLI:

```bash
cp lib/firebase_options.dart.example lib/firebase_options.dart
cp android/app/google-services.example.json android/app/google-services.json
# or:  flutterfire configure
```

**3. Point the app at your API**

Edit [`lib/services/api_constants.dart`](lib/services/api_constants.dart) and set
`apiHost` to where the PHP API is served (e.g. `10.0.2.2` for an Android emulator
reaching a local server).

The API itself lives in [`../public/api/`](../public/api); see the root
[README](../README.md) for serving it.

**4. Run**

```bash
flutter run
```

## Project layout

```
lib/
├── main.dart                     app entry, Firebase init, routes
├── screens/                      landing, login, and the role dashboards
├── dashboard_pages/              Company/Admin panels
├── pm_dashboard_pages/           Project Manager panels
├── tl_dashboard_pages/           Team Lead panels
├── tm_dashboard_pages/           Team Member panels
├── widgets/                      shared UI
└── services/
    ├── api_constants.dart        API host / base URL
    ├── api_http.dart             authenticated HTTP wrapper (adds the token)
    └── api_service.dart          small request helper
```

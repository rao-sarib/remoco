/// API endpoint configuration.
///
/// Point [apiHost] at wherever the PHP API is served:
///   - Android emulator reaching a local server:  "10.0.2.2"
///   - a physical device on your LAN:              your machine's LAN IP
///   - a deployed server:                          its host or domain
///
/// [apiBaseUrl] assumes the API is served under `/remoco_app/api/`. Adjust the
/// path if you serve it elsewhere (for example, pointing a web server's document
/// root at the repository's `public/` makes the path `/api/`).
const String apiHost = "10.0.2.2";
const String apiBaseUrl = "http://$apiHost/remoco_app/api/";

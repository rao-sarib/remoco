/// Authenticated HTTP shim.
///
/// The API now requires a bearer token on every call. This wraps the small slice
/// of `package:http` the app uses (`get`, `post`, and multipart uploads) so the
/// `Authorization` header is attached automatically, and re-exports the rest of
/// the package unchanged.
///
/// Screens keep their existing code — they only swap the import line from
/// `package:http/http.dart` to this file. The token is held in memory and set at
/// login; it is not persisted, so the app requires a fresh sign-in on each launch
/// (its existing behaviour).
library;

import 'dart:convert';
import 'package:http/http.dart' as http;

// Re-export everything except the top-level helpers we override below, so
// `http.Response`, `http.MultipartRequest`, `http.MultipartFile`, etc. still
// resolve through this import.
export 'package:http/http.dart' hide get, post;

/// In-memory bearer token. Null until login sets it.
String? authToken;

/// Store the token returned by a login endpoint.
void setToken(String? token) => authToken = token;

/// Forget the token (call on logout).
void clearToken() => authToken = null;

Map<String, String> _withAuth(Map<String, String>? headers) {
  final merged = <String, String>{...?headers};
  final token = authToken;
  if (token != null && token.isNotEmpty) {
    merged['Authorization'] = 'Bearer $token';
  }
  return merged;
}

/// GET with the auth header attached.
Future<http.Response> get(Uri url, {Map<String, String>? headers}) {
  return http.get(url, headers: _withAuth(headers));
}

/// POST with the auth header attached.
Future<http.Response> post(
  Uri url, {
  Map<String, String>? headers,
  Object? body,
  Encoding? encoding,
}) {
  return http.post(url, headers: _withAuth(headers), body: body, encoding: encoding);
}

/// A [http.MultipartRequest] with the auth header pre-set. Use this in place of
/// `http.MultipartRequest(...)` for authenticated file uploads.
http.MultipartRequest authMultipartRequest(String method, Uri url) {
  final request = http.MultipartRequest(method, url);
  final token = authToken;
  if (token != null && token.isNotEmpty) {
    request.headers['Authorization'] = 'Bearer $token';
  }
  return request;
}

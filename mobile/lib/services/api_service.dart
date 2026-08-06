import 'dart:convert';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:remoco_app/services/api_constants.dart';

class ApiService {
  static const String baseUrl = "http://$apiHost/remoco_app/api/";

  static Future<Map<String, dynamic>> post(String endpoint, Map<String, dynamic> body) async {
    try {
      final response = await http.post(
        Uri.parse(baseUrl + endpoint),
        headers: {"Content-Type": "application/json"},
        body: json.encode(body),
      ).timeout(const Duration(seconds: 10));

      return json.decode(response.body);
    } catch (e) {
      return {"status": "error", "message": "Network error: $e"};
    }
  }

  // Database initialization (calls PHP setup script)
  static Future<Map<String, dynamic>> initializeDatabase() async {
    return await post("initialize.php", {});
  }
}
import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:remoco_app/services/api_constants.dart';
import 'dart:convert';

class PmHomePage extends StatefulWidget {
  final String employeeId;
  final String employeeName;

  const PmHomePage({
    Key? key,
    required this.employeeId,
    required this.employeeName,
  }) : super(key: key);

  @override
  State<PmHomePage> createState() => _PmHomePageState();
}

class _PmHomePageState extends State<PmHomePage> {
  Map<String, dynamic> stats = {};
  bool isLoading = true;
  String error = '';

  @override
  void initState() {
    super.initState();
    fetchStats();
  }

  Future<void> fetchStats() async {
    final url = Uri.parse('http://$apiHost/remoco_app/api/pm_dashboard_stats.php');

    try {
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'employee_id': widget.employeeId}),
      );

      final data = json.decode(response.body);
      if (data['status'] == 'success') {
        setState(() {
          stats = data['data'];
          isLoading = false;
        });
      } else {
        setState(() {
          error = data['message'] ?? 'Failed to load stats';
          isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        error = 'Error fetching stats: $e';
        isLoading = false;
      });
    }
  }

  Widget buildStatCard(IconData icon, String label, dynamic value) {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            Icon(icon, size: 36, color: Colors.blue[700]),
            SizedBox(height: 12),
            Text(
              '$value',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.black87),
            ),
            SizedBox(height: 8),
            Text(label, style: TextStyle(fontSize: 16, color: Colors.grey[700])),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Center(child: CircularProgressIndicator());
    }
    if (error.isNotEmpty) {
      return Center(child: Text(error, style: TextStyle(color: Colors.red)));
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Center(
            child: Column(
              children: [
                Text(
                  'Welcome, ${widget.employeeName}',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.blue[700]),
                  textAlign: TextAlign.center,
                ),
                SizedBox(height: 8),
                Text(
                  'Your task statistics at a glance',
                  style: TextStyle(fontSize: 16, color: Colors.grey[600]),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
          SizedBox(height: 24),
          GridView.count(
            crossAxisCount: MediaQuery.of(context).size.width > 600 ? 3 : 2,
            shrinkWrap: true,
            physics: NeverScrollableScrollPhysics(),
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
            children: [
              buildStatCard(Icons.assignment, 'Total Tasks', stats['total_tasks']),
              buildStatCard(Icons.hourglass_empty, 'Not Started', stats['not_started']),
              buildStatCard(Icons.autorenew, 'In Progress', stats['in_progress']),
              buildStatCard(Icons.check_circle, 'Completed', stats['completed']),
              buildStatCard(Icons.priority_high, 'High Priority', stats['high_priority']),
            ],
          ),
        ],
      ),
    );
  }
}

import 'dart:convert';
import 'package:remoco_app/services/api_constants.dart';
import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;

class HomePage extends StatefulWidget {
  final String companyId; // ADD THIS

  const HomePage({Key? key, required this.companyId}) : super(key: key); // ADD THIS

  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int totalEmployees = 0;
  int projectManagers = 0;
  int teamLeads = 0;
  bool isLoading = true;
  String errorMessage = '';

  @override
  void initState() {
    super.initState();
    fetchDashboardStats();
  }

  Future<void> fetchDashboardStats() async {
    try {
      final response = await http.post(
        Uri.parse('http://$apiHost/remoco_app/api/get_dashboard_stats.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'company_id': widget.companyId}), // USE THE companyId FROM WIDGET
      );

      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['error'] == null) {
        setState(() {
          totalEmployees = data['total_employees'];
          projectManagers = data['project_managers'];
          teamLeads = data['team_leads'];
          isLoading = false;
        });
      } else {
        setState(() {
          errorMessage = data['error'] ?? 'Failed to load dashboard data.';
          isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        errorMessage = 'Error fetching dashboard data: $e';
        isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (errorMessage.isNotEmpty) {
      return Center(child: Text(errorMessage, style: const TextStyle(color: Colors.red)));
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const Text(
            "HOME",
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          const Text(
            "Welcome to your company dashboard. Here's a quick overview of your workforce.",
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 30),
          Wrap(
            spacing: 20,
            runSpacing: 20,
            alignment: WrapAlignment.center,
            children: [
              buildStatCard("Total Employees", totalEmployees, Icons.group),
              buildStatCard("Project Managers", projectManagers, Icons.person),
              buildStatCard("Team Leads", teamLeads, Icons.people),
            ],
          ),
        ],
      ),
    );
  }

  Widget buildStatCard(String title, int value, IconData icon) {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 250,
        padding: const EdgeInsets.symmetric(vertical: 30, horizontal: 20),
        child: Column(
          children: [
            Icon(icon, size: 40, color: Colors.blue[700]),
            const SizedBox(height: 15),
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            Text(
              value.toString(),
              style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: Colors.black87),
            ),
          ],
        ),
      ),
    );
  }
}

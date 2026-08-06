import 'dart:convert';
import 'package:remoco_app/services/api_constants.dart';
import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;

class EmployeesPage extends StatefulWidget {
  final String companyId;

  const EmployeesPage({Key? key, required this.companyId}) : super(key: key);

  @override
  State<EmployeesPage> createState() => _EmployeesPageState();
}

class _EmployeesPageState extends State<EmployeesPage> {
  List<dynamic> employees = [];
  bool isLoading = true;
  String errorMessage = '';

  @override
  void initState() {
    super.initState();
    fetchEmployees();
  }

  Future<void> fetchEmployees() async {
    setState(() {
      isLoading = true;
      errorMessage = '';
    });

    try {
      final response = await http.post(
        Uri.parse('http://$apiHost/remoco_app/api/get_employees.php'),
        body: {'company_id': widget.companyId},
      );

      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          setState(() => employees = data['employees']);
        } else {
          setState(() => errorMessage = data['message'] ?? 'Unknown error');
        }
      } else {
        setState(() => errorMessage = 'Server error: ${response.statusCode}');
      }
    } catch (e) {
      setState(() => errorMessage = 'Error: $e');
    } finally {
      setState(() => isLoading = false);
    }
  }

  Widget _buildEmployeeCard(Map<String, dynamic> employee) {
    return Card(
      elevation: 3,
      margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Colors.blue.shade700,
          child: Text(
            employee['employee_name'].toString().substring(0, 1).toUpperCase(),
            style: const TextStyle(color: Colors.white),
          ),
        ),
        title: Text(employee['employee_name'] ?? '',
            style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('CNIC: ${employee['cnic']}'),
            Text('Email: ${employee['email']}'),
            Text('Designation: ${employee['designation']}'),
            Text(
              'Created: ${employee['created_at']}',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
        isThreeLine: true,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false, // remove back button
        title: const Text(
          'Employees',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
        backgroundColor: Colors.blue.shade700,
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage.isNotEmpty
          ? Center(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Text(
            errorMessage,
            style: const TextStyle(color: Colors.red, fontSize: 16),
            textAlign: TextAlign.center,
          ),
        ),
      )
          : employees.isEmpty
          ? const Center(
        child: Text(
          'No employees found.',
          style: TextStyle(fontSize: 16),
        ),
      )
          : RefreshIndicator(
        onRefresh: fetchEmployees,
        child: ListView.builder(
          itemCount: employees.length,
          itemBuilder: (context, index) =>
              _buildEmployeeCard(employees[index]),
        ),
      ),
    );
  }
}

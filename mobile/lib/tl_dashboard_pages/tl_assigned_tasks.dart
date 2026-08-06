import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:remoco_app/services/api_constants.dart';
import 'dart:convert';

import 'package:remoco_app/screens/tl_assign.dart';

class TlAssignedTasks extends StatefulWidget {
  final String employeeId;
  final String companyId;

  const TlAssignedTasks({
    Key? key,
    required this.employeeId,
    required this.companyId,
  }) : super(key: key);

  @override
  _TlAssignedTasksState createState() => _TlAssignedTasksState();
}

class _TlAssignedTasksState extends State<TlAssignedTasks> {
  List<Map<String, dynamic>> _tasks = [];
  bool _isLoading = true;

  String apiBaseUrl = "http://$apiHost/remoco_app/api";

  @override
  void initState() {
    super.initState();
    _fetchAssignedTasks();
  }

  Future<void> _fetchAssignedTasks() async {
    try {
      final response = await http.post(
        Uri.parse('$apiBaseUrl/get_tl_assigned_tasks.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'team_lead_id': widget.employeeId}),
      );
      final data = json.decode(response.body);

      if (data['status'] == 'success') {
        setState(() {
          _tasks = List<Map<String, dynamic>>.from(data['tasks']);
          _isLoading = false;
        });
      } else {
        _showSnackbar(data['message'] ?? 'Failed to load tasks');
        setState(() => _isLoading = false);
      }
    } catch (e) {
      _showSnackbar('Error loading tasks: $e');
      setState(() => _isLoading = false);
    }
  }

  void _showSnackbar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false, // removes back button
        centerTitle: true, // centers the title
        backgroundColor: const Color(0xff2563eb),
        title: const Text(
          "Assigned Tasks",
          style: TextStyle(
            color: Colors.white, // white text color
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _tasks.isEmpty
          ? const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle_outline, color: Colors.green, size: 60),
            SizedBox(height: 20),
            Text("No Tasks Assigned", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            SizedBox(height: 10),
            Text("You don't have any tasks assigned right now."),
          ],
        ),
      )
          : ListView.separated(
        padding: const EdgeInsets.all(15),
        itemCount: _tasks.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (_, index) {
          final task = _tasks[index];
          final dueDate = task['due_date'] ?? 'Not set';
          final priority = task['task_priority'] ?? '';
          final status = task['task_status'] ?? '';

          Color priorityColor;
          switch (priority) {
            case 'High':
              priorityColor = Colors.red.shade600;
              break;
            case 'Medium':
              priorityColor = Colors.orange.shade600;
              break;
            case 'Low':
              priorityColor = Colors.green.shade600;
              break;
            default:
              priorityColor = Colors.grey.shade600;
          }

          Color statusColor;
          switch (status) {
            case 'Not Started':
              statusColor = Colors.grey.shade700;
              break;
            case 'In Progress':
              statusColor = Colors.blue.shade600;
              break;
            case 'Completed':
              statusColor = Colors.green.shade600;
              break;
            default:
              statusColor = Colors.black;
          }

          return Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            elevation: 3,
            child: Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "#${task['task_id']} - ${task['title']}",
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xff1e293b)),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      const Icon(Icons.calendar_today, size: 18, color: Colors.grey),
                      const SizedBox(width: 6),
                      Text(dueDate, style: TextStyle(color: Colors.grey.shade800)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Chip(
                        label: Text(priority, style: const TextStyle(color: Colors.white)),
                        backgroundColor: priorityColor,
                      ),
                      const SizedBox(width: 10),
                      Chip(
                        label: Text(status, style: const TextStyle(color: Colors.white)),
                        backgroundColor: statusColor,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.bottomRight,
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => TlAssignPage(
                              taskId: int.parse(task['task_id'].toString()),
                              companyId: widget.companyId,
                            ),
                          ),
                        );
                      },
                      icon: const Icon(Icons.open_in_new, color: Colors.white),
                      label: const Text("Open", style: TextStyle(color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xff2563eb),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

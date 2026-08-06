import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_constants.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:remoco_app/tm_dashboard_pages/tm_updatetask.dart';

class TmAssignedTasksPage extends StatefulWidget {
  final int employeeId;
  final String companyId;

  const TmAssignedTasksPage({
    super.key,
    required this.employeeId,
    required this.companyId,
  });

  @override
  State<TmAssignedTasksPage> createState() => _TmAssignedTasksPageState();
}

class _TmAssignedTasksPageState extends State<TmAssignedTasksPage> {
  bool loading = true;
  String error = '';
  List tasks = [];

  @override
  void initState() {
    super.initState();
    fetchTasks();
  }

  Future<void> fetchTasks() async {
    try {
      final response = await http.get(Uri.parse(
          'http://$apiHost/remoco_app/api/get_tm_assigned_tasks.php?employee_id=${widget.employeeId}&company_id=${widget.companyId}'));
      final data = json.decode(response.body);
      print('Raw response: ${response.body}');

      if (response.statusCode == 200) {
        setState(() {
          tasks = data['tasks'];
          loading = false;
        });
      } else {
        setState(() {
          error = data['error'] ?? 'Failed to fetch tasks';
          loading = false;
        });
      }
    } catch (e) {
      setState(() {
        error = e.toString();
        loading = false;
      });
    }
  }

  Widget buildBadge(String text, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(
      color: color.withOpacity(0.15),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      text,
      style: TextStyle(color: color, fontWeight: FontWeight.bold),
    ),
  );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false, // removes back button
        backgroundColor: Colors.blue.shade800,
        elevation: 0,
        centerTitle: true, // centers title
        title: const Text(
          'My Assigned Tasks',
          style: TextStyle(
            color: Colors.white, // white title text
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error.isNotEmpty
          ? Center(child: Text(error))
          : tasks.isEmpty
          ? const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle,
                color: Colors.green, size: 80),
            SizedBox(height: 20),
            Text("No Tasks Assigned",
                style: TextStyle(fontSize: 18)),
          ],
        ),
      )
          : ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: tasks.length,
        itemBuilder: (context, idx) {
          final task = tasks[idx];
          final dueDate = task['due_date'] ?? 'Not set';
          final priority = task['task_priority'] ?? 'N/A';
          final status = task['task_status'] ?? 'Unknown';

          Color priorityColor;
          switch (priority) {
            case 'High':
              priorityColor = Colors.red;
              break;
            case 'Medium':
              priorityColor = Colors.orange;
              break;
            case 'Low':
              priorityColor = Colors.green;
              break;
            default:
              priorityColor = Colors.grey;
          }

          Color statusColor;
          switch (status) {
            case 'Not Started':
              statusColor = Colors.grey;
              break;
            case 'In Progress':
              statusColor = Colors.blue;
              break;
            case 'Completed':
              statusColor = Colors.green;
              break;
            default:
              statusColor = Colors.grey;
          }

          return Card(
            elevation: 4,
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12)),
            margin: const EdgeInsets.only(bottom: 16),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Task #${task['task_id']}',
                      style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.blue.shade800)),
                  const SizedBox(height: 8),
                  Text(task['title'] ?? 'No Title',
                      style: const TextStyle(fontSize: 18)),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.date_range,
                          size: 18, color: Colors.grey),
                      const SizedBox(width: 6),
                      Text('Due: $dueDate'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      buildBadge(priority, priorityColor),
                      const SizedBox(width: 8),
                      buildBadge(status, statusColor),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerRight,
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => TmUpdateTask(
                              companyId: widget.companyId,
                              taskId: task['task_id'],
                            ),
                          ),
                        );
                      },
                      icon: const Icon(Icons.arrow_forward),
                      label: const Text('Open'),
                      style: ElevatedButton.styleFrom(
                        foregroundColor: Colors.white,
                        backgroundColor: Colors.blue.shade800,
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

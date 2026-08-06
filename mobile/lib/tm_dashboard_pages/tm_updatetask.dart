import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:remoco_app/services/api_constants.dart';
import 'dart:convert';

class TmUpdateTask extends StatefulWidget {
  final String companyId;
  final int taskId;

  const TmUpdateTask({super.key, required this.companyId, required this.taskId});

  @override
  State<TmUpdateTask> createState() => _TmUpdateTaskState();
}

class _TmUpdateTaskState extends State<TmUpdateTask> {
  Map<String, dynamic>? task;
  List<dynamic> checkpoints = [];
  Set<int> completedCheckpoints = {};
  bool loading = true;

  @override
  void initState() {
    super.initState();
    fetchTaskDetails();
  }

  Future<void> fetchTaskDetails() async {
    final response = await http.get(Uri.parse(
        "http://$apiHost/remoco_app/api/get_tm_task_details.php?task_id=${widget.taskId}"));
    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      setState(() {
        task = data['task'];
        checkpoints = data['checkpoints'];
        completedCheckpoints = checkpoints
            .where((cp) => cp['status'] == 'Completed')
            .map<int>((cp) => int.parse(cp['checkpoint_id'].toString()))
            .toSet();
        loading = false;
      });
    } else {
      throw Exception('Failed to load task details');
    }
  }

  Future<void> updateCheckpoints() async {
    final response = await http.post(
      Uri.parse("http://$apiHost/remoco_app/api/update_tm_task_checkpoints.php"),
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'task_id': widget.taskId,
        'checkpoints': completedCheckpoints.toList(),
      }),
    );
    if (response.statusCode == 200) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Checkpoints updated successfully!')));
      fetchTaskDetails();
    } else {
      throw Exception('Failed to update checkpoints');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // subtle light gray
      appBar: AppBar(
        title: const Text('Update Task'),
        backgroundColor: Colors.blue[800],
        elevation: 0,
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // TASK HEADER CARD
            Card(
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16)),
              elevation: 5,
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      task?['title'] ?? 'No Title',
                      style: const TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF1E293B)),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      "Description: ${task?['task_description'] ?? 'No description'}",
                      style: const TextStyle(
                          fontSize: 16, color: Color(0xFF475569)),
                    ),
                    const Divider(height: 30, thickness: 1.5),
                    Row(
                      children: [
                        Icon(Icons.calendar_today, color: Colors.blue[800]),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            "Due Date: ${task?['due_date'] ?? 'Not set'}",
                            style: const TextStyle(fontSize: 16),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.priority_high,
                            color: Colors.orange[700]),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            "Priority: ${task?['task_priority'] ?? ''}",
                            style: TextStyle(
                              fontSize: 16,
                              color: (task?['task_priority'] ?? '').toLowerCase() == 'high'
                                  ? Colors.red
                                  : (task?['task_priority'] ?? '').toLowerCase() == 'medium'
                                  ? Colors.orange
                                  : Colors.green,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.info, color: Colors.green[700]),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            "Status: ${task?['task_status'] ?? ''}",
                            style: const TextStyle(
                                fontSize: 16, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 25),

            // CHECKPOINTS HEADER
            const Text(
              "Checkpoints",
              style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF1E293B)),
            ),
            const SizedBox(height: 12),

            // CHECKPOINTS CARD
            Card(
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16)),
              elevation: 3,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: checkpoints.isEmpty
                    ? const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Text(
                      "No checkpoints defined for this task.",
                      style: TextStyle(
                          fontSize: 16, color: Colors.grey),
                    ),
                  ),
                )
                    : Column(
                  children: checkpoints
                      .map((cp) => CheckboxListTile(
                    contentPadding:
                    const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    shape: RoundedRectangleBorder(
                        borderRadius:
                        BorderRadius.circular(8)),
                    tileColor: const Color(0xFFF8FAFC),
                    title: Text(
                      cp['checkpoint'],
                      style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w500),
                    ),
                    value: completedCheckpoints.contains(
                        int.parse(cp['checkpoint_id']
                            .toString())),
                    onChanged: (val) {
                      setState(() {
                        if (val == true) {
                          completedCheckpoints.add(
                              int.parse(cp['checkpoint_id']
                                  .toString()));
                        } else {
                          completedCheckpoints.remove(
                              int.parse(cp['checkpoint_id']
                                  .toString()));
                        }
                      });
                    },
                    secondary: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: cp['status'] == 'Completed'
                            ? Colors.green[100]
                            : Colors.red[100],
                        borderRadius:
                        BorderRadius.circular(20),
                      ),
                      child: Text(
                        cp['status'],
                        style: TextStyle(
                            fontSize: 12,
                            color: cp['status'] ==
                                'Completed'
                                ? Colors.green[800]
                                : Colors.red[800],
                            fontWeight: FontWeight.w600),
                      ),
                    ),
                  ))
                      .toList(),
                ),
              ),
            ),

            const SizedBox(height: 30),

            // UPDATE BUTTON
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: updateCheckpoints,
                icon: const Icon(Icons.save),
                label: const Text("Update Checkpoints"),
                style: ElevatedButton.styleFrom(
                  foregroundColor: Colors.white,
                  backgroundColor: Colors.blue[800],
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  textStyle: const TextStyle(
                      fontSize: 18, fontWeight: FontWeight.w600),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                  elevation: 5,
                ),
              ),
            )
          ],
        ),
      ),
    );
  }
}

import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_constants.dart';
import 'package:remoco_app/services/api_http.dart' as http;

class TlAssignPage extends StatefulWidget {
  final int taskId;
  final String companyId; // <-- add this

  const TlAssignPage({
    Key? key,
    required this.taskId,
    required this.companyId, // <-- add this
  }) : super(key: key);

  @override
  State<TlAssignPage> createState() => _TlAssignPageState();
}


class _TlAssignPageState extends State<TlAssignPage> {
  bool loading = true;
  Map<String, dynamic>? taskData;
  String error = '';
  List<TextEditingController> checkpointControllers = [TextEditingController()];
  int selectedTm1 = 0, selectedTm2 = 0, selectedTm3 = 0;

  @override
  void initState() {
    super.initState();
    fetchTaskDetails();
  }

  Future<void> fetchTaskDetails() async {


    try {
      final res = await http.get(Uri.parse(
          'http://$apiHost/remoco_app/api/get_task_details.php?task_id=${widget.taskId}&company_id=${widget.companyId}'
      ));
      if (res.statusCode == 200) {
        setState(() {
          taskData = json.decode(res.body);
          loading = false;
        });
      } else {
        setState(() {
          error = json.decode(res.body)['error'] ?? 'Failed to fetch task';
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

  Future<void> assignTask() async {
    if (selectedTm1 == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Select Team Member 1')),
      );
      return;
    }

    List<String> checkpoints = checkpointControllers
        .map((c) => c.text.trim())
        .where((c) => c.isNotEmpty)
        .toList();

    if (checkpoints.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Add at least one checkpoint')),
      );
      return;
    }

    try {
      // Build the body map with simple key-values
      Map<String, String> body = {
        'task_id': widget.taskId.toString(),
        'tm1': selectedTm1.toString(),
        'tm2': selectedTm2.toString(),
        'tm3': selectedTm3.toString(),
      };

      // Manually add each checkpoint to the body with keys like checkpoints[0], checkpoints[1], ...
      for (int i = 0; i < checkpoints.length; i++) {
        body['checkpoints[$i]'] = checkpoints[i];
      }

      print('Sending assignTask POST data: $body');

      final res = await http.post(
        Uri.parse('http://$apiHost/remoco_app/api/assign_task.php'),
        body: body,
      );

      final data = json.decode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Task assigned successfully')),
        );
        Navigator.pop(context);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['error'] ?? 'Assignment failed')),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  }


  Widget buildBadge(String text, Color color) => Container(
    padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
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
    if (loading) return Center(child: CircularProgressIndicator());
    if (error.isNotEmpty) return Center(child: Text(error));
    var task = taskData!['task'];
    var members = taskData!['team_members'];

    return Scaffold(
      appBar: AppBar(
        title: Text('Assign Task #${task['task_id']}'),
        backgroundColor: Colors.blue.shade800,
      ),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // --- Task Details Card ---
            Card(
              elevation: 5,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Task Details', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.blue.shade800)),
                    Divider(),
                    Text('Title:', style: TextStyle(fontWeight: FontWeight.bold)),
                    Text('${task['title']}', style: TextStyle(fontSize: 16)),
                    SizedBox(height: 8),
                    Text('Description:', style: TextStyle(fontWeight: FontWeight.bold)),
                    Text('${task['task_description'] ?? 'N/A'}', style: TextStyle(fontSize: 16)),
                    SizedBox(height: 8),
                    Row(
                      children: [
                        Text('Priority:', style: TextStyle(fontWeight: FontWeight.bold)),
                        SizedBox(width: 10),
                        buildBadge(
                          task['task_priority'] ?? 'N/A',
                          (task['task_priority'] == 'High')
                              ? Colors.red
                              : (task['task_priority'] == 'Medium')
                              ? Colors.orange
                              : Colors.green,
                        ),
                      ],
                    ),
                    SizedBox(height: 8),
                    Row(
                      children: [
                        Text('Status:', style: TextStyle(fontWeight: FontWeight.bold)),
                        SizedBox(width: 10),
                        buildBadge(
                          task['task_status'] ?? 'Unknown',
                          (task['task_status'] == 'Completed')
                              ? Colors.green
                              : (task['task_status'] == 'In Progress')
                              ? Colors.blue
                              : Colors.grey,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            SizedBox(height: 20),

            // --- Team Assignment Card ---
            Card(
              elevation: 5,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Assign Team Members', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.blue.shade800)),
                    Divider(),
                    DropdownButtonFormField<int>(
                      value: selectedTm1 == 0 ? null : selectedTm1,
                      decoration: InputDecoration(labelText: 'Team Member 1 *'),
                      items: members.map<DropdownMenuItem<int>>((m) {
                        return DropdownMenuItem<int>(
                            value: int.tryParse(m['employee_id'].toString()) ?? 0,
                            child: Text('${m['employee_name']} (ID: ${m['employee_id']})'));
                      }).toList(),
                      onChanged: (val) => setState(() => selectedTm1 = val!),
                    ),
                    SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      value: selectedTm2 == 0 ? null : selectedTm2,
                      decoration: InputDecoration(labelText: 'Team Member 2'),
                      items: [
                        DropdownMenuItem(value: 0, child: Text('-- None --')),
                        ...members.map<DropdownMenuItem<int>>((m) {
                          return DropdownMenuItem<int>(
                              value: int.tryParse(m['employee_id'].toString()) ?? 0,
                              child: Text('${m['employee_name']} (ID: ${m['employee_id']})'));
                        }).toList()
                      ],
                      onChanged: (val) => setState(() => selectedTm2 = val!),
                    ),
                    SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      value: selectedTm3 == 0 ? null : selectedTm3,
                      decoration: InputDecoration(labelText: 'Team Member 3'),
                      items: [
                        DropdownMenuItem(value: 0, child: Text('-- None --')),
                        ...members.map<DropdownMenuItem<int>>((m) {
                          return DropdownMenuItem<int>(
                              value: int.tryParse(m['employee_id'].toString()) ?? 0,
                              child: Text('${m['employee_name']} (ID: ${m['employee_id']})'));
                        }).toList()
                      ],
                      onChanged: (val) => setState(() => selectedTm3 = val!),
                    ),
                  ],
                ),
              ),
            ),
            SizedBox(height: 20),

            // --- Checkpoints Card ---
            Card(
              elevation: 5,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Create Checkpoints', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.blue.shade800)),
                    Divider(),
                    ListView.builder(
                      shrinkWrap: true,
                      physics: NeverScrollableScrollPhysics(),
                      itemCount: checkpointControllers.length,
                      itemBuilder: (context, idx) => Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: checkpointControllers[idx],
                              decoration: InputDecoration(
                                labelText: 'Checkpoint ${idx + 1}',
                                suffixIcon: IconButton(
                                  icon: Icon(Icons.delete, color: Colors.red),
                                  onPressed: () {
                                    setState(() {
                                      if (checkpointControllers.length > 1) {
                                        checkpointControllers.removeAt(idx);
                                      } else {
                                        checkpointControllers[idx].clear();
                                      }
                                    });
                                  },
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 12),
                    ElevatedButton.icon(
                      onPressed: () {
                        setState(() {
                          checkpointControllers.add(TextEditingController());
                        });
                      },
                      icon: Icon(Icons.add),
                      label: Text('Add Checkpoint'),
                      style: ElevatedButton.styleFrom(
                          foregroundColor: Colors.white,
                          backgroundColor: Colors.blue.shade800),
                    ),
                  ],
                ),
              ),
            ),
            SizedBox(height: 20),

            // --- Assign Button ---
            Center(
              child: ElevatedButton.icon(
                onPressed: assignTask,
                icon: Icon(Icons.check_circle),
                label: Text('Assign Task'),
                style: ElevatedButton.styleFrom(
                  foregroundColor: Colors.white,
                  padding: EdgeInsets.symmetric(horizontal: 40, vertical: 16),
                  textStyle: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  backgroundColor: Colors.green.shade700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

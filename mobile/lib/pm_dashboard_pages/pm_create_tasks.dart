import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_constants.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'dart:convert';

class PmCreateTasks extends StatefulWidget {
  final String employeeId;
  final String companyId;

  const PmCreateTasks({
    Key? key,
    required this.employeeId,
    required this.companyId,
  }) : super(key: key);

  @override
  _PmCreateTasksState createState() => _PmCreateTasksState();
}

class _PmCreateTasksState extends State<PmCreateTasks> {
  final _formKey = GlobalKey<FormState>();

  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  String? _selectedPriority;
  String? _selectedTeamLead;
  String? _dueDate;
  bool _isSubmitting = false;
  List<Map<String, String>> _teamLeads = [];

  String apiBaseUrl = "http://$apiHost/remoco_app/api";

  @override
  void initState() {
    super.initState();
    _fetchTeamLeads();
  }

  Future<void> _fetchTeamLeads() async {
    try {
      final response = await http.post(
        Uri.parse('$apiBaseUrl/get_team_leads.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'company_id': widget.companyId}),
      );

      final data = json.decode(response.body);

      if (data['status'] == 'success') {
        setState(() {
          _teamLeads = (data['team_leads'] as List)
              .map((lead) => {
            'employee_id': lead['employee_id'].toString(),
            'employee_name': lead['employee_name'].toString(),
          })
              .toList();
        });
      } else {
        _showSnackbar(data['message'] ?? 'Failed to load team leads');
      }
    } catch (e) {
      _showSnackbar('Error loading team leads: $e');
    }
  }

  void _showSnackbar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _submitTask() async {
    if (!_formKey.currentState!.validate() || _dueDate == null) {
      _showSnackbar('Please fill all required fields');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final response = await http.post(
        Uri.parse('$apiBaseUrl/create_task.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'pm_id': widget.employeeId,
          'title': _titleController.text.trim(),
          'description': _descriptionController.text.trim(),
          'due_date': _dueDate,
          'priority': _selectedPriority,
          'team_lead_id': _selectedTeamLead,
          'company_id': widget.companyId,
        }),
      );
      print('API response: ${response.body}');

      final data = json.decode(response.body);

      if (data['status'] == 'success') {
        _showSnackbar(data['message']);
        _formKey.currentState!.reset();
        setState(() {
          _titleController.clear();
          _descriptionController.clear();
          _selectedPriority = null;
          _selectedTeamLead = null;
          _dueDate = null;
        });
      } else {
        _showSnackbar(data['message'] ?? 'Failed to create task');
      }
    } catch (e) {
      _showSnackbar('Error: $e');
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Color(0xfff8f9fc),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Card(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
          elevation: 5,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "Create New Task",
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: Colors.blue[700],
                    ),
                  ),
                  const SizedBox(height: 20),
                  TextFormField(
                    controller: _titleController,
                    decoration: _inputDecoration("Task Title *"),
                    maxLength: 150,
                    validator: (val) => val == null || val.isEmpty ? "Title is required" : null,
                  ),
                  const SizedBox(height: 15),
                  TextFormField(
                    controller: _descriptionController,
                    decoration: _inputDecoration("Task Description"),
                    maxLines: 4,
                  ),
                  const SizedBox(height: 15),
                  Row(
                    children: [
                      Expanded(
                        child: InkWell(
                          onTap: () async {
                            final picked = await showDatePicker(
                              context: context,
                              initialDate: DateTime.now(),
                              firstDate: DateTime.now(),
                              lastDate: DateTime(2100),
                            );
                            if (picked != null) {
                              setState(() => _dueDate = picked.toIso8601String().split("T").first);
                            }
                          },
                          child: InputDecorator(
                            decoration: _inputDecoration("Due Date *"),
                            child: Text(_dueDate ?? "Select date"),
                          ),
                        ),
                      ),
                      const SizedBox(width: 15),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _selectedPriority,
                          items: ['High', 'Medium', 'Low']
                              .map((p) => DropdownMenuItem(value: p, child: Text(p)))
                              .toList(),
                          decoration: _inputDecoration("Priority *"),
                          onChanged: (val) => setState(() => _selectedPriority = val),
                          validator: (val) => val == null ? "Select priority" : null,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 15),
                  DropdownButtonFormField<String>(
                    value: _selectedTeamLead,
                    items: _teamLeads
                        .map((lead) => DropdownMenuItem(
                      value: lead['employee_id'],
                      child: Text("${lead['employee_id']} - ${lead['employee_name']}"),
                    ))
                        .toList(),
                    decoration: _inputDecoration("Assign to Team Lead *"),
                    onChanged: (val) => setState(() => _selectedTeamLead = val),
                    validator: (val) => val == null ? "Select team lead" : null,
                  ),
                  const SizedBox(height: 25),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      OutlinedButton.icon(
                        onPressed: _isSubmitting ? null : () => _formKey.currentState!.reset(),
                        icon: Icon(Icons.refresh),
                        label: Text("Reset"),
                        style: OutlinedButton.styleFrom(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                      ElevatedButton.icon(
                        onPressed: _isSubmitting ? null : _submitTask,
                        icon: _isSubmitting
                            ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : Icon(Icons.add_circle_outline, color: Colors.white),
                        label: Text(
                          _isSubmitting ? "Creating..." : "Create Task",
                          style: TextStyle(color: Colors.white),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue[700],
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          padding: EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                        ),
                      ),
                    ],
                  )
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Color(0xff1976d2)),
      ),
    );
  }
}

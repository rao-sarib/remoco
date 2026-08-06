import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_constants.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'dart:convert';

class EmployeeRegistrationPage extends StatefulWidget {
  final String companyId;

  const EmployeeRegistrationPage({Key? key, required this.companyId})
      : super(key: key);

  @override
  _EmployeeRegistrationPageState createState() =>
      _EmployeeRegistrationPageState();
}

class _EmployeeRegistrationPageState extends State<EmployeeRegistrationPage> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _cnicController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String _designation = '';
  bool _loading = false;
  String? _successMessage;
  String? _errorMessage;

  Future<void> _registerEmployee() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _successMessage = null;
      _errorMessage = null;
    });

    final url = Uri.parse('http://$apiHost/remoco_app/api/register_employee.php');

    try {
      final response = await http.post(
        url,
        body: {
          'employee_name': _nameController.text.trim(),
          'cnic': _cnicController.text.trim(),
          'email': _emailController.text.trim(),
          'password': _passwordController.text.trim(),
          'designation': _designation,
          'company_id': widget.companyId,
        },
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        final Map<String, dynamic> data = json.decode(response.body);
        setState(() {
          _loading = false;

          final status = (data['status'] ?? '').toString();
          final message = (data['message'] ?? 'Unknown error').toString();

          if (status == 'success') {
            _successMessage = message;
            _nameController.clear();
            _cnicController.clear();
            _emailController.clear();
            _passwordController.clear();
            _designation = '';
          } else {
            _errorMessage = message;
          }
        });
      } else {
        setState(() {
          _loading = false;
          _errorMessage = 'Server error: ${response.statusCode}';
        });
      }
    } catch (e) {
      setState(() {
        _loading = false;
        _errorMessage = 'Request failed: $e';
      });
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _cnicController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const primaryColor = Color(0xFF2563EB); // consistent REMOCO color

    return Scaffold(
      body: SingleChildScrollView(
        child: Column(
          children: [
            Container(
              color: primaryColor,
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 30),
              child: const Center(
                child: Text(
                  'Register Employee',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20.0),
              child: Form(
                key: _formKey,
                child: Column(
                  children: [
                    if (_successMessage != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 15),
                        decoration: BoxDecoration(
                          color: Colors.green[50],
                          border: Border.all(color: Colors.green),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle, color: Colors.green),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _successMessage!,
                                style: const TextStyle(color: Colors.green),
                              ),
                            ),
                          ],
                        ),
                      ),
                    if (_errorMessage != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 15),
                        decoration: BoxDecoration(
                          color: Colors.red[50],
                          border: Border.all(color: Colors.red),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error, color: Colors.red),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: const TextStyle(color: Colors.red),
                              ),
                            ),
                          ],
                        ),
                      ),
                    TextFormField(
                      controller: _nameController,
                      decoration: _inputDecoration('Employee Name'),
                      validator: (value) =>
                      value!.isEmpty ? 'Name is required' : null,
                    ),
                    const SizedBox(height: 15),
                    TextFormField(
                      controller: _cnicController,
                      keyboardType: TextInputType.number,
                      decoration: _inputDecoration('CNIC (e.g., 1234512345671)'),
                      validator: (value) =>
                      value!.isEmpty ? 'CNIC is required' : null,
                    ),
                    const SizedBox(height: 15),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: _inputDecoration('Email'),
                      validator: (value) =>
                      value!.isEmpty ? 'Email is required' : null,
                    ),
                    const SizedBox(height: 15),
                    DropdownButtonFormField<String>(
                      decoration: _inputDecoration('Designation'),
                      value: _designation.isEmpty ? null : _designation,
                      items: [
                        'Project Manager',
                        'Team Lead',
                        'Team Member',
                        'Guest'
                      ]
                          .map((role) => DropdownMenuItem(
                        value: role,
                        child: Text(role),
                      ))
                          .toList(),
                      onChanged: (value) {
                        setState(() {
                          _designation = value ?? '';
                        });
                      },
                      validator: (value) =>
                      value == null || value.isEmpty
                          ? 'Select a designation'
                          : null,
                    ),
                    const SizedBox(height: 15),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: true,
                      decoration: _inputDecoration('Password (min 8 chars)'),
                      validator: (value) =>
                      value != null && value.length >= 8
                          ? null
                          : 'Minimum 8 characters required',
                    ),
                    const SizedBox(height: 25),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: primaryColor,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        onPressed: _loading ? null : _registerEmployee,
                        child: _loading
                            ? const SizedBox(
                          height: 22,
                          width: 22,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                            : const Text(
                          'Register Employee',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label) => InputDecoration(
    labelText: label,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
    contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 18),
  );
}

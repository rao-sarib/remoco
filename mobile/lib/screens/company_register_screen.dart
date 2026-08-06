import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_service.dart';

class CompanyRegisterScreen extends StatefulWidget {
  const CompanyRegisterScreen({super.key});

  @override
  State<CompanyRegisterScreen> createState() => _CompanyRegisterScreenState();
}

class _CompanyRegisterScreenState extends State<CompanyRegisterScreen> {
  final _formKey = GlobalKey<FormState>();

  final TextEditingController _companyIdController = TextEditingController();
  final TextEditingController _companyNameController = TextEditingController();
  final TextEditingController _ntnController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmPasswordController = TextEditingController();

  String? _companySector;
  bool _isRegistered = false;
  bool _loading = false;

  Map<String, String> _errors = {};

  Future<void> _registerCompany() async {
    setState(() {
      _errors = {};
    });

    if (!_formKey.currentState!.validate()) return;

    setState(() => _loading = true);

    final response = await ApiService.post("company_register.php", {
      "company_id": _companyIdController.text.trim(),
      "company_name": _companyNameController.text.trim(),
      "is_registered": _isRegistered ? "1" : "0",
      "company_ntn": _isRegistered ? _ntnController.text.trim() : "",
      "company_sector": _companySector,
      "email": _emailController.text.trim(),
      "password": _passwordController.text,
      "confirm_password": _confirmPasswordController.text,
    });

    setState(() => _loading = false);

    if (response["status"] == "success") {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text("Success"),
          content: Text(response["message"] ?? "Company registered successfully!"),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.pop(context); // go back to previous screen
              },
              child: const Text("OK"),
            ),
          ],
        ),
      );
    } else if (response["status"] == "error" && response["errors"] is Map) {
      setState(() {
        _errors = Map<String, String>.from(response["errors"]);
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(response["message"] ?? "Registration failed"),
      ));
    }
  }

  Widget _buildErrorText(String field) {
    return _errors.containsKey(field)
        ? Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Text(
        _errors[field]!,
        style: const TextStyle(color: Colors.red, fontSize: 12),
      ),
    )
        : const SizedBox.shrink();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Register Company")),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              children: [
                // Company ID
                TextFormField(
                  controller: _companyIdController,
                  decoration: const InputDecoration(
                    labelText: "Company ID * (Format: ABC123)",
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return "Company ID is required";
                    }
                    if (!RegExp(r'^[A-Z]{3}\d{3}$').hasMatch(value)) {
                      return "Company ID must be 3 uppercase letters + 3 digits";
                    }
                    return null;
                  },
                ),
                _buildErrorText("company_id"),

                const SizedBox(height: 15),

                // Company Name
                TextFormField(
                  controller: _companyNameController,
                  decoration: const InputDecoration(labelText: "Company Name *"),
                  validator: (value) =>
                  value == null || value.isEmpty ? "Company name is required" : null,
                ),
                _buildErrorText("company_name"),

                const SizedBox(height: 15),

                // Is Registered
                Row(
                  children: [
                    const Text("Is your company registered? *"),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Row(
                        children: [
                          Radio(
                            value: true,
                            groupValue: _isRegistered,
                            onChanged: (val) => setState(() => _isRegistered = val!),
                          ),
                          const Text("Yes"),
                          Radio(
                            value: false,
                            groupValue: _isRegistered,
                            onChanged: (val) => setState(() => _isRegistered = val!),
                          ),
                          const Text("No"),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                _isRegistered
                    ? Column(
                  children: [
                    TextFormField(
                      controller: _ntnController,
                      decoration: const InputDecoration(labelText: "NTN Number *"),
                      validator: (value) {
                        if (_isRegistered && (value == null || value.isEmpty)) {
                          return "NTN is required for registered companies";
                        }
                        return null;
                      },
                    ),
                    _buildErrorText("company_ntn"),
                  ],
                )
                    : const SizedBox.shrink(),

                const SizedBox(height: 15),

                // Sector
                DropdownButtonFormField<String>(
                  decoration: const InputDecoration(labelText: "Company Sector *"),
                  items: const [
                    DropdownMenuItem(value: "Technology", child: Text("Technology")),
                    DropdownMenuItem(value: "Finance", child: Text("Finance")),
                    DropdownMenuItem(value: "Healthcare", child: Text("Healthcare")),
                    DropdownMenuItem(value: "Education", child: Text("Education")),
                    DropdownMenuItem(value: "Retail", child: Text("Retail")),
                    DropdownMenuItem(value: "Manufacturing", child: Text("Manufacturing")),
                    DropdownMenuItem(value: "Other", child: Text("Other")),
                  ],
                  onChanged: (val) => setState(() => _companySector = val),
                  validator: (value) =>
                  value == null || value.isEmpty ? "Company sector is required" : null,
                ),
                _buildErrorText("company_sector"),

                const SizedBox(height: 15),

                // Email
                TextFormField(
                  controller: _emailController,
                  decoration: const InputDecoration(labelText: "Official Email *"),
                  keyboardType: TextInputType.emailAddress,
                  validator: (value) {
                    if (value == null || value.isEmpty) return "Email is required";
                    if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(value)) {
                      return "Invalid email format";
                    }
                    return null;
                  },
                ),
                _buildErrorText("email"),

                const SizedBox(height: 15),

                // Password & Confirm Password
                TextFormField(
                  controller: _passwordController,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: "Password *"),
                  validator: (value) {
                    if (value == null || value.isEmpty) return "Password is required";
                    if (value.length < 8) return "Password must be at least 8 characters";
                    return null;
                  },
                ),
                _buildErrorText("password"),

                const SizedBox(height: 15),

                TextFormField(
                  controller: _confirmPasswordController,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: "Confirm Password *"),
                  validator: (value) {
                    if (value != _passwordController.text) return "Passwords do not match";
                    return null;
                  },
                ),
                _buildErrorText("confirm_password"),

                const SizedBox(height: 30),

                // Register Button
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _registerCompany,
                    child: _loading
                        ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                        : const Text("Register Company"),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

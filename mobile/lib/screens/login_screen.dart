import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'dart:convert';
import 'package:remoco_app/services/api_constants.dart';
import 'package:remoco_app/screens/admin_dashboard.dart';
import 'package:remoco_app/screens/g_dashboard.dart';
import 'package:remoco_app/screens/pm_dashboard.dart';
import 'package:remoco_app/screens/tl_dashboard.dart';
import 'package:remoco_app/screens/tm_dashboard.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool _isEmployeeLogin = true;
  bool _isLoading = false;
  String _errorMessage = '';
  String _successMessage = '';

  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _companyIdController = TextEditingController();
  final TextEditingController _companyPasswordController = TextEditingController();

  final String _apiBaseUrl = 'http://$apiHost/remoco_app/api';

  @override
  void initState() {
    super.initState();
    // Arriving at the login screen (including via logout) clears any old token.
    http.clearToken();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: LayoutBuilder(
        builder: (context, constraints) {
          if (constraints.maxWidth > 600) {
            // Tablet/Desktop: side by side
            return Row(
              children: [
                SizedBox(
                  width: constraints.maxWidth * 0.5,
                  child: _buildBrandingSection(),
                ),
                SizedBox(
                  width: constraints.maxWidth * 0.5,
                  child: _buildLoginSection(),
                ),
              ],
            );
          } else {
            // Mobile: branding stacked above login
            return SingleChildScrollView(
              child: Column(
                children: [
                  SizedBox(
                    height: 250,
                    width: double.infinity,
                    child: _buildBrandingSection(),
                  ),
                  _buildLoginSection(),
                ],
              ),
            );
          }
        },
      ),
    );
  }

  Widget _buildBrandingSection() {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF2563EB), Color(0xFF1E40AF)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -150,
            left: -150,
            child: Container(
              width: 400,
              height: 400,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withOpacity(0.05),
              ),
            ),
          ),
          Positioned(
            bottom: -100,
            right: -100,
            child: Container(
              width: 300,
              height: 300,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withOpacity(0.05),
              ),
            ),
          ),
          Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.groups, size: 64, color: Colors.white),
                const SizedBox(height: 20),
                ShaderMask(
                  shaderCallback: (bounds) {
                    return const LinearGradient(
                      colors: [Colors.white, Color(0xFFCBD5E1)],
                    ).createShader(bounds);
                  },
                  child: const Text(
                    "REMOCO",
                    style: TextStyle(
                      fontSize: 48,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  "Remote Workforce Management",
                  style: TextStyle(
                    fontSize: 24,
                    color: Colors.white70,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLoginSection() {
    return Container(
      color: Colors.white,
      child: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 60),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  "Welcome Back",
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF2563EB),
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 10),
                const Text(
                  "Sign in to your account",
                  style: TextStyle(
                    fontSize: 18,
                    color: Color(0xFF64748B),
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 40),
                if (_errorMessage.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(15),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFEBEE),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFFFCDD2)),
                    ),
                    child: Text(
                      _errorMessage,
                      style: const TextStyle(color: Color(0xFFC62828)),
                    ),
                  ),
                if (_successMessage.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(15),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE8F5E9),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFC8E6C9)),
                    ),
                    child: Text(
                      _successMessage,
                      style: const TextStyle(color: Color(0xFF2E7D32)),
                    ),
                  ),
                if (_errorMessage.isNotEmpty || _successMessage.isNotEmpty)
                  const SizedBox(height: 20),
                Container(
                  decoration: BoxDecoration(
                    color: const Color(0xFFF0F2F5),
                    borderRadius: BorderRadius.circular(50),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          onPressed: () {
                            setState(() {
                              _isEmployeeLogin = false;
                              _errorMessage = '';
                              _successMessage = '';
                            });
                          },
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            backgroundColor: !_isEmployeeLogin
                                ? Colors.white
                                : Colors.transparent,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(50),
                            ),
                          ),
                          child: Text(
                            "Company Login",
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: !_isEmployeeLogin
                                  ? const Color(0xFF2563EB)
                                  : const Color(0xFF555555),
                            ),
                          ),
                        ),
                      ),
                      Expanded(
                        child: TextButton(
                          onPressed: () {
                            setState(() {
                              _isEmployeeLogin = true;
                              _errorMessage = '';
                              _successMessage = '';
                            });
                          },
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            backgroundColor: _isEmployeeLogin
                                ? Colors.white
                                : Colors.transparent,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(50),
                            ),
                          ),
                          child: Text(
                            "Employee Login",
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: _isEmployeeLogin
                                  ? const Color(0xFF2563EB)
                                  : const Color(0xFF555555),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),
                if (!_isEmployeeLogin) _buildCompanyLoginForm(),
                if (_isEmployeeLogin) _buildEmployeeLoginForm(),
                const SizedBox(height: 30),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    TextButton(
                      onPressed: () {},
                      child: const Text(
                        "Forgot Password?",
                        style: TextStyle(color: Color(0xFF2563EB)),
                      ),
                    ),
                    const SizedBox(width: 20),
                    TextButton(
                      onPressed: () {},
                      child: const Text(
                        "Help Center",
                        style: TextStyle(color: Color(0xFF2563EB)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                const Text(
                  "© 2023 REMOCO. All rights reserved.",
                  style: TextStyle(
                    color: Color(0xFF777777),
                    fontSize: 14,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
          if (_isLoading)
            Positioned.fill(
              child: Container(
                color: Colors.black.withOpacity(0.3),
                child: const Center(
                  child: CircularProgressIndicator(
                    valueColor: AlwaysStoppedAnimation<Color>(Color(0xFF2563EB)),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildCompanyLoginForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextFormField(
          controller: _companyIdController,
          decoration: InputDecoration(
            labelText: "Company ID",
            hintText: "Enter your company ID (e.g., ABC123)",
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          ),
        ),
        const SizedBox(height: 20),
        TextFormField(
          controller: _companyPasswordController,
          obscureText: true,
          decoration: InputDecoration(
            labelText: "Password",
            hintText: "Enter your password",
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          ),
        ),
        const SizedBox(height: 30),
        ElevatedButton(
          onPressed: _handleCompanyLogin,
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF2563EB),
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          child: const Text(
            "Login to Company Account",
            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ),
        const SizedBox(height: 15),
        OutlinedButton(
          onPressed: () {
            Navigator.pushNamed(context, '/company_register');
          },
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
            side: const BorderSide(color: Color(0xFF2563EB)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          child: const Text(
            "Register New Company",
            style: TextStyle(color: Color(0xFF2563EB), fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }

  Widget _buildEmployeeLoginForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextFormField(
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(
            labelText: "Email Address",
            hintText: "you@company.com",
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          ),
        ),
        const SizedBox(height: 20),
        TextFormField(
          controller: _passwordController,
          obscureText: true,
          decoration: InputDecoration(
            labelText: "Password",
            hintText: "Enter your password",
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          ),
        ),
        const SizedBox(height: 20),
        TextFormField(
          controller: _companyIdController,
          decoration: InputDecoration(
            labelText: "Company ID",
            hintText: "Enter your company ID (e.g., ABC123)",
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          ),
        ),
        const SizedBox(height: 30),
        ElevatedButton(
          onPressed: _handleEmployeeLogin,
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF2563EB),
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          child: const Text(
            "Login to Employee Account",
            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }

  void _handleCompanyLogin() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
      _successMessage = '';
    });

    final companyId = _companyIdController.text.trim();
    final companyPassword = _companyPasswordController.text.trim();

    if (companyId.isEmpty || companyPassword.isEmpty) {
      setState(() {
        _errorMessage = 'Company ID and password are required';
        _isLoading = false;
      });
      return;
    }

    try {
      final response = await http.post(
        Uri.parse('$_apiBaseUrl/company_login.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'company_id': companyId,
          'password': companyPassword,
        }),
      );

      final responseData = json.decode(response.body);

      if (responseData['status'] == 'success') {
        // Store the bearer token for every later authenticated API call.
        http.setToken(responseData['token']);
        setState(() {
          _successMessage = 'Company login successful!';
        });

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => AdminDashboard(
              companyId: responseData['company_id'],
              companyName: responseData['company_name'],
            ),
          ),
        );
      } else {
        setState(() {
          _errorMessage = responseData['message'] ?? 'Login failed';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error: $e';
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _handleEmployeeLogin() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
      _successMessage = '';
    });

    final email = _emailController.text.trim();
    final password = _passwordController.text.trim();
    final companyId = _companyIdController.text.trim();

    if (email.isEmpty || password.isEmpty || companyId.isEmpty) {
      setState(() {
        _errorMessage = 'All fields are required for employee login';
        _isLoading = false;
      });
      return;
    }

    try {
      final response = await http.post(
        Uri.parse('$_apiBaseUrl/employee_login.php'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'email': email,
          'password': password,
          'company_id': companyId,
        }),
      );

      print('Response body: ${response.body}');

      final responseData = json.decode(response.body);

      if (responseData['status'] == 'success') {
        // Store the bearer token for every later authenticated API call.
        http.setToken(responseData['token']);
        setState(() {
          _successMessage = 'Employee login successful!';
        });

        final designation = (responseData['designation'] as String).trim().toLowerCase();


        Widget dashboardPage;

        final employeeId = responseData['employee_id'].toString();

        switch (designation.toLowerCase()) {  // also normalize casing for safety
          case 'project manager':
            dashboardPage = PmDashboard(
              employeeId: employeeId,
              employeeName: responseData['employee_name'],
              email: responseData['email'],
              companyId: responseData['company_id'],
              designation: designation,
            );
            break;
          case 'team lead':
            dashboardPage = TlDashboard(
              employeeId: employeeId,
              employeeName: responseData['employee_name'],
              email: responseData['email'],
              companyId: responseData['company_id'],
              designation: designation,
            );
            break;
          case 'team member':
            dashboardPage = TmDashboard(
              employeeId: employeeId,
              employeeName: responseData['employee_name'],
              email: responseData['email'],
              companyId: responseData['company_id'],
              designation: designation,
            );
            break;
          case 'guest':
            dashboardPage = GDashboard(
              employeeId: employeeId,
              employeeName: responseData['employee_name'],
              email: responseData['email'],
              companyId: responseData['company_id'],
              designation: designation,
            );
            break;
          default:
            setState(() {
              _errorMessage = 'Unknown designation: ${responseData['designation']}';
            });
            _isLoading = false;
            return;
        }



        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => dashboardPage),
        );
      } else {
        setState(() {
          _errorMessage = responseData['message'] ?? 'Login failed';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error: $e';
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }



  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _companyIdController.dispose();
    _companyPasswordController.dispose();
    super.dispose();
  }
}

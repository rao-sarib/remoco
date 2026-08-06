import 'package:flutter/material.dart';
import 'package:remoco_app/services/api_service.dart';
import 'package:remoco_app/widgets/feature_card.dart';

class LandingScreen extends StatefulWidget {
  const LandingScreen({super.key});

  @override
  State<LandingScreen> createState() => _LandingScreenState();
}

class _LandingScreenState extends State<LandingScreen> {
  String _dbStatus = "Initializing database...";

  @override
  void initState() {
    super.initState();
    _initializeDatabase();
  }

  Future<void> _initializeDatabase() async {
    try {
      final response = await ApiService.initializeDatabase();
      if (response['status'] == 'success') {
        setState(() {
          _dbStatus = "Database ready";
        });
      } else {
        setState(() {
          _dbStatus = "Database error: ${response['message']}";
        });
      }
    } catch (e) {
      setState(() {
        _dbStatus = "Connection error: $e";
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            children: [
              // Header
              Container(
                decoration: BoxDecoration(
                  color: const Color.fromRGBO(255, 255, 255, 0.95),
                  boxShadow: const [
                    BoxShadow(
                      color: Colors.black12,
                      blurRadius: 10,
                      offset: Offset(0, 2),
                    ),
                  ],
                ),
                padding: const EdgeInsets.symmetric(vertical: 15),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      if (constraints.maxWidth > 600) {
                        // Desktop layout
                        return Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            // Logo
                            Row(
                              children: [
                                const Icon(Icons.groups,
                                    size: 32, color: Color(0xFF2563EB)),
                                const SizedBox(width: 12),
                                ShaderMask(
                                  shaderCallback: (bounds) {
                                    return const LinearGradient(
                                      colors: [
                                        Color(0xFF2563EB),
                                        Color(0xFF1E40AF)
                                      ],
                                    ).createShader(bounds);
                                  },
                                  child: const Text(
                                    "REMOCO",
                                    style: TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.w800,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            // Navigation with horizontal scroll
                            SingleChildScrollView(
                              scrollDirection: Axis.horizontal,
                              child: Row(
                                children: [
                                  _buildNavButton("Home"),
                                  _buildNavButton("Features"),
                                  _buildNavButton("Solutions"),
                                  const SizedBox(width: 15),
                                  _buildGetStartedButton(context),
                                ],
                              ),
                            ),
                          ],
                        );
                      } else {
                        // Mobile layout
                        return Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            // Logo
                            Row(
                              children: [
                                const Icon(Icons.groups,
                                    size: 32, color: Color(0xFF2563EB)),
                                const SizedBox(width: 12),
                                ShaderMask(
                                  shaderCallback: (bounds) {
                                    return const LinearGradient(
                                      colors: [
                                        Color(0xFF2563EB),
                                        Color(0xFF1E40AF)
                                      ],
                                    ).createShader(bounds);
                                  },
                                  child: const Text(
                                    "REMOCO",
                                    style: TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.w800,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            // Hamburger menu
                            IconButton(
                              icon: const Icon(Icons.menu),
                              onPressed: () => _showMobileMenu(context),
                            ),
                          ],
                        );
                      }
                    },
                  ),
                ),
              ),

              // Hero Section
              Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFE6F0FF), Color(0xFFF0F4F8)],
                  ),
                ),
                padding: const EdgeInsets.symmetric(vertical: 60, horizontal: 20),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  child: SizedBox(
                    width: double.infinity,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ShaderMask(
                          shaderCallback: (bounds) {
                            return const LinearGradient(
                              colors: [Color(0xFF1E293B), Color(0xFF1E40AF)],
                            ).createShader(bounds);
                          },
                          child: const Text(
                            "Collaborate Seamlessly with Your Remote Team",
                            style: TextStyle(
                              fontSize: 36,
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                            ),
                          ),
                        ),
                        const SizedBox(height: 20),
                        const Text(
                          "REMOCO brings your team together with real-time communication, task management, and secure collaboration tools - all in one intuitive platform designed for distributed teams.",
                          style: TextStyle(
                            fontSize: 16,
                            color: Color(0xFF64748B),
                          ),
                        ),
                        const SizedBox(height: 30),
                        Wrap(
                          spacing: 15,
                          runSpacing: 15,
                          children: [
                            ElevatedButton(
                              onPressed: () {
                                Navigator.pushNamed(context, '/login');
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF2563EB),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 20, vertical: 12),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(30)),
                                elevation: 4,
                                shadowColor: const Color(0x402563EB),
                              ),
                              child: const Text(
                                "Get Started",
                                style: TextStyle(
                                    fontSize: 14,
                                    color: Colors.white,
                                    fontWeight: FontWeight.w600),
                              ),
                            ),
                            OutlinedButton(
                              onPressed: () {
                                showDialog(
                                  context: context,
                                  builder: (context) => AlertDialog(
                                    title: const Text("Watch Demo"),
                                    content: const Text("Demo video would play here"),
                                    actions: [
                                      TextButton(
                                        onPressed: () => Navigator.pop(context),
                                        child: const Text("OK"),
                                      ),
                                    ],
                                  ),
                                );
                              },
                              style: OutlinedButton.styleFrom(
                                side: const BorderSide(color: Color(0xFF2563EB)),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(30)),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 20, vertical: 12),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.play_circle,
                                      color: Color(0xFF2563EB), size: 20),
                                  SizedBox(width: 8),
                                  Text(
                                    "Watch Demo",
                                    style: TextStyle(
                                        fontSize: 14,
                                        color: Color(0xFF2563EB),
                                        fontWeight: FontWeight.w600),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              // Features Section
              Container(
                color: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  child: Column(
                    children: [
                      const Text(
                        "Powerful Collaboration Features",
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 15),
                      Container(
                        width: 80,
                        height: 4,
                        decoration: BoxDecoration(
                          color: const Color(0xFF2563EB),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                      const SizedBox(height: 30),
                      LayoutBuilder(
                        builder: (context, constraints) {
                          return GridView.count(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            crossAxisCount: constraints.maxWidth > 600 ? 3 : 1,
                            childAspectRatio: 0.9,
                            crossAxisSpacing: 20,
                            mainAxisSpacing: 20,
                            children: const [
                              FeatureCard(
                                icon: Icons.chat,
                                title: "Real-Time Chat",
                                description:
                                "Communicate instantly with your team through channels and direct messages with end-to-end encryption.",
                              ),
                              FeatureCard(
                                icon: Icons.task_alt,
                                title: "Task Management",
                                description:
                                "Create, assign, and organize tasks with priorities and deadlines. Manage workloads efficiently.",
                              ),
                              FeatureCard(
                                icon: Icons.trending_up,
                                title: "Progress Tracking",
                                description:
                                "Monitor task completion rates, visualize team performance, and identify bottlenecks with analytics.",
                              ),
                            ],
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),

              // Footer
              Container(
                color: const Color(0xFF0F172A),
                padding: const EdgeInsets.only(top: 40, bottom: 20, left: 20, right: 20),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.groups, size: 32, color: Colors.white),
                          const SizedBox(width: 12),
                          ShaderMask(
                            shaderCallback: (bounds) {
                              return const LinearGradient(
                                colors: [Color(0xFF2563EB), Color(0xFF1E40AF)],
                              ).createShader(bounds);
                            },
                            child: const Text(
                              "REMOCO",
                              style: TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 40),
                        child: Text(
                          "Empowering distributed teams to collaborate effectively with secure, all-in-one tools designed for the modern workplace.",
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Color(0xFFCBD5E1)),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          _buildSocialIcon(Icons.chat),
                          const SizedBox(width: 15),
                          _buildSocialIcon(Icons.link),
                          const SizedBox(width: 15),
                          _buildSocialIcon(Icons.facebook),
                          const SizedBox(width: 15),
                          _buildSocialIcon(Icons.camera_alt),
                        ],
                      ),
                      const SizedBox(height: 30),
                      Text(
                        "© ${DateTime.now().year} REMOCO. All rights reserved.",
                        style: const TextStyle(
                            color: Color(0xFF94A3B8), fontSize: 14),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _dbStatus,
                        style: const TextStyle(
                            color: Color(0xFF64748B), fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavButton(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: TextButton(
        onPressed: () {},
        child: Text(text,
            style: const TextStyle(
                color: Color(0xFF1E293B), fontWeight: FontWeight.w500)),
      ),
    );
  }

  Widget _buildGetStartedButton(BuildContext context) {
    return ElevatedButton(
      onPressed: () {
        Navigator.pushNamed(context, '/login');
      },
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFF2563EB),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(30)),
        elevation: 4,
        shadowColor: const Color(0x402563EB),
      ),
      child: const Text(
        "Get Started",
        style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
            fontSize: 14),
      ),
    );
  }

  void _showMobileMenu(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildMobileMenuItem("Home"),
          _buildMobileMenuItem("Features"),
          _buildMobileMenuItem("Solutions"),
          Padding(
            padding: const EdgeInsets.all(15),
            child: _buildGetStartedButton(context),
          ),
        ],
      ),
    );
  }

  Widget _buildMobileMenuItem(String text) {
    return ListTile(
      title: Text(text,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w500)),
      onTap: () => Navigator.pop(context),
    );
  }

  Widget _buildSocialIcon(IconData icon) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Icon(icon, color: Colors.white, size: 20),
    );
  }
}
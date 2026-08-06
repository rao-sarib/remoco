import 'package:flutter/material.dart';
import 'package:remoco_app/dashboard_pages/home_page.dart';
import 'package:remoco_app/dashboard_pages/employee_registration_page.dart';
import 'package:remoco_app/dashboard_pages/employees_page.dart';
import 'package:remoco_app/dashboard_pages/tasks_page.dart';
import 'package:remoco_app/dashboard_pages/reports_analytics_page.dart';
import 'package:remoco_app/dashboard_pages/set_alerts_page.dart';

class AdminDashboard extends StatefulWidget {
  final String companyId;
  final String companyName;

  const AdminDashboard({
    Key? key,
    required this.companyId,
    required this.companyName,
  }) : super(key: key);

  @override
  _AdminDashboardState createState() => _AdminDashboardState();
}

class _AdminDashboardState extends State<AdminDashboard> {
  String currentPage = 'home';
  bool isCollapsed = true;

  Widget getPage() {
    switch (currentPage) {
      case 'home':
        return HomePage(companyId: widget.companyId);
      case 'employee_registration':
        return EmployeeRegistrationPage(companyId: widget.companyId);
      case 'employees':
        return EmployeesPage(companyId: widget.companyId);
      // case 'tasks':
      //   return TasksPage(companyId: widget.companyId);
      // case 'reports_analytics':
      //   return ReportsAnalyticsPage(companyId: widget.companyId);
      // case 'set_alerts':
      //   return SetAlertsPage(companyId: widget.companyId);
      default:
        return Center(child: Text('Page not found'));
    }
  }

  void collapseSidebar() {
    setState(() => isCollapsed = true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            GestureDetector(
              onTap: collapseSidebar,
              child: Column(
                children: [
                  Container(
                    color: Colors.white,
                    child: Column(
                      children: [
                        // First line: hamburger + logout
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              IconButton(
                                icon: Icon(Icons.menu, size: 28, color: Colors.blue[700]),
                                onPressed: () => setState(() => isCollapsed = !isCollapsed),
                              ),
                              ElevatedButton.icon(
                                onPressed: () => Navigator.of(context).pop(),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.blue[700],
                                  padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                ),
                                icon: Text(
                                  "ID: ${widget.companyId}",
                                  style: TextStyle(color: Colors.white, fontSize: 14),
                                ),
                                label: Icon(Icons.logout, color: Colors.white),
                              ),
                            ],
                          ),
                        ),
                        // Second line: centered dashboard title
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          child: Center(
                            child: Text(
                              "REMOCO - Admin Dashboard",
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: Colors.blue[700],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(child: getPage()),
                ],
              ),
            ),
            // Sidebar overlay
            if (!isCollapsed)
              Positioned(
                top: 0,
                bottom: 0,
                left: 0,
                child: Container(
                  width: 260,
                  color: Colors.blue[700],
                  child: Column(
                    children: [
                      Container(
                        height: 70,
                        padding: EdgeInsets.symmetric(horizontal: 10),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              "REMOCO",
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            IconButton(
                              icon: Icon(Icons.close, color: Colors.white),
                              onPressed: collapseSidebar,
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: ListView(
                          children: [
                            sidebarItem(Icons.home, "HOME", 'home'),
                            sidebarItem(Icons.person_add, "EMPLOYEE REGISTRATION", 'employee_registration'),
                            sidebarItem(Icons.group, "EMPLOYEES", 'employees'),
                            sidebarItem(Icons.task, "TASKS", 'tasks'),
                            sidebarItem(Icons.bar_chart, "REPORTS & ANALYTICS", 'reports_analytics'),
                            sidebarItem(Icons.notifications, "SET ALERTS", 'set_alerts'),
                          ],
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

  Widget sidebarItem(IconData icon, String label, String page) {
    bool isActive = currentPage == page;
    return InkWell(
      onTap: () => setState(() {
        currentPage = page;
        isCollapsed = true; // collapse after selecting a page
      }),
      child: Container(
        color: isActive ? Colors.blue[800] : Colors.transparent,
        padding: EdgeInsets.symmetric(horizontal: 10, vertical: 15),
        child: Row(
          children: [
            Icon(icon, color: Colors.white),
            SizedBox(width: 15),
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: Colors.white),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

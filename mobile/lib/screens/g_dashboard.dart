import 'package:flutter/material.dart';

class GDashboard extends StatefulWidget {
  final String employeeId;
  final String employeeName;
  final String email;
  final String companyId;
  final String designation;

  const GDashboard({
    Key? key,
    required this.employeeId,
    required this.employeeName,
    required this.email,
    required this.companyId,
    required this.designation,
  }) : super(key: key);

  @override
  _GDashboardState createState() => _GDashboardState();
}

class _GDashboardState extends State<GDashboard> {
  String currentPage = 'home';
  bool isCollapsed = true;

  Widget getPage() {
    switch (currentPage) {
      case 'home':
        return Center(child: Text("HOME page placeholder"));
      case 'chats':
        return Center(child: Text("CHATS page placeholder"));
      case 'tasks':
        return Center(child: Text("TASKS page placeholder"));
      case 'reports_analytics':
        return Center(child: Text("REPORTS & ANALYTICS page placeholder"));
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
                                  widget.employeeName.length > 5
                                      ? widget.employeeName.substring(0, 5).toUpperCase()
                                      : widget.employeeName.toUpperCase(),
                                  style: TextStyle(color: Colors.white, fontSize: 14),
                                ),
                                label: Icon(Icons.logout, color: Colors.white),
                              ),
                            ],
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          child: Center(
                            child: Text(
                              "REMOCO - Guest Dashboard",
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
                            Text("REMOCO", style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                            IconButton(icon: Icon(Icons.close, color: Colors.white), onPressed: collapseSidebar),
                          ],
                        ),
                      ),
                      Expanded(
                        child: ListView(
                          children: [
                            sidebarItem(Icons.home, "HOME", 'home'),
                            sidebarItem(Icons.chat, "CHATS", 'chats'),
                            sidebarItem(Icons.task, "TASKS", 'tasks'),
                            sidebarItem(Icons.bar_chart, "REPORTS & ANALYTICS", 'reports_analytics'),
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
        isCollapsed = true;
      }),
      child: Container(
        color: isActive ? Colors.blue[800] : Colors.transparent,
        padding: EdgeInsets.symmetric(horizontal: 10, vertical: 15),
        child: Row(
          children: [
            Icon(icon, color: Colors.white),
            SizedBox(width: 15),
            Expanded(child: Text(label, style: TextStyle(color: Colors.white))),
          ],
        ),
      ),
    );
  }
}

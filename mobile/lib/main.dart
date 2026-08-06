import 'package:flutter/material.dart';
import 'package:flutter/services.dart'; // Add this import
import 'package:firebase_core/firebase_core.dart';
import 'firebase_options.dart';
import 'package:remoco_app/screens/company_register_screen.dart';
import 'package:remoco_app/screens/login_screen.dart';
import 'package:remoco_app/screens/splash_screen.dart';
import 'package:remoco_app/screens/landing_screen.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:remoco_app/screens/tl_assign.dart'; // Make sure this is imported

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting();
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp])
      .then((_) {
    runApp(const MyApp());
  });
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'REMOCO',
      debugShowCheckedModeBanner: false,
      initialRoute: '/splash',
      routes: {
        '/splash': (context) => const SplashScreen(),
        '/landing': (context) => const LandingScreen(),
        '/login': (context) => const LoginScreen(),
        '/company_register': (context) => const CompanyRegisterScreen(),
        //'/tl_assign': (context) => TlAssignPage(taskId: taskId),

        // Add '/login' route when you create the login screen
      },
      theme: ThemeData(
        primaryColor: const Color(0xFF2563EB),
        fontFamily: 'Segoe UI',
        scaffoldBackgroundColor: const Color(0xFFF0F4F8),
      ),
    );
  }
}
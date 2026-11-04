// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// مسارات التطبيق (GoRouter).
abstract final class Routes {
  // بدون تسجيل دخول
  static const splash = '/splash';
  static const login = '/login';
  static const server = '/server';

  // تبويبات الشريط السفلي (StatefulShellRoute)
  static const home = '/home';
  static const reports = '/reports';
  static const map = '/map';
  static const profile = '/profile';

  // شاشات كاملة فوق الشريط السفلي
  static const notifications = '/notifications';
  static const departmentStats = '/department-stats';
  static const editProfile = '/edit-profile';
  static const changePassword = '/change-password';
  static const settings = '/settings';
  static const media = '/media';

  static String report(String id) => '/report/$id';

  static const publicPaths = {login, server};
}

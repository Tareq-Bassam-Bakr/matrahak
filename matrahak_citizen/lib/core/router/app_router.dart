// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/map/presentation/map_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/profile/presentation/change_password_screen.dart';
import '../../features/profile/presentation/edit_profile_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/reports/domain/report.dart';
import '../../features/reports/presentation/create_report_screen.dart';
import '../../features/reports/presentation/home_screen.dart';
import '../../features/reports/presentation/location_picker_screen.dart';
import '../../features/reports/presentation/media_viewer_screen.dart';
import '../../features/reports/presentation/my_reports_screen.dart';
import '../../features/reports/presentation/report_details_screen.dart';
import '../../features/reports/presentation/report_submitted_screen.dart';
import '../../features/settings/presentation/server_settings_screen.dart';
import '../../features/settings/presentation/settings_screen.dart';
import '../../features/shell/home_shell.dart';
import '../widgets/state_views.dart';
import 'routes.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');

/// GoRouter مع إعادة توجيه حسب حالة الجلسة:
/// غير معروفة => شاشة البداية، غير مسجّل => الدخول، مسجّل => الرئيسية.
final routerProvider = Provider<GoRouter>((ref) {
  final authStatus = ValueNotifier<AuthStatus>(ref.read(authControllerProvider).status);
  ref.listen(authControllerProvider.select((s) => s.status), (_, next) => authStatus.value = next);

  final router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: Routes.splash,
    refreshListenable: authStatus,
    redirect: (context, state) {
      final status = authStatus.value;
      final location = state.matchedLocation;
      if (status == AuthStatus.unknown) return location == Routes.splash ? null : Routes.splash;
      if (status == AuthStatus.unauthenticated) {
        return Routes.publicPaths.contains(location) ? null : Routes.login;
      }
      if (location == Routes.splash || location == Routes.login || location == Routes.register) return Routes.home;
      return null;
    },
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.server, builder: (_, _) => const ServerSettingsScreen()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.map, builder: (_, _) => const MapScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.reports, builder: (_, _) => const MyReportsScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: Routes.profile, builder: (_, _) => const ProfileScreen())],
          ),
        ],
      ),
      GoRoute(
        path: Routes.newReport,
        builder: (_, state) =>
            CreateReportScreen(initialCategoryId: int.tryParse(state.uri.queryParameters['category'] ?? '')),
      ),
      GoRoute(
        path: Routes.pickLocation,
        builder: (_, state) {
          final extra = state.extra;
          return LocationPickerScreen(initial: extra is LatLng ? extra : null);
        },
      ),
      GoRoute(
        path: Routes.reportSubmitted,
        builder: (_, state) {
          final extra = state.extra;
          return extra is Report ? ReportSubmittedScreen(report: extra) : const _MissingPage();
        },
      ),
      GoRoute(
        path: '/report/:id',
        builder: (_, state) => ReportDetailsScreen(reportId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: Routes.media,
        builder: (_, state) {
          final extra = state.extra;
          return extra is MediaViewerArgs ? MediaViewerScreen(args: extra) : const _MissingPage();
        },
      ),
      GoRoute(path: Routes.notifications, builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: Routes.editProfile, builder: (_, _) => const EditProfileScreen()),
      GoRoute(path: Routes.changePassword, builder: (_, _) => const ChangePasswordScreen()),
      GoRoute(path: Routes.settings, builder: (_, _) => const SettingsScreen()),
    ],
    errorBuilder: (_, _) => const _MissingPage(),
  );

  ref.onDispose(() {
    router.dispose();
    authStatus.dispose();
  });
  return router;
});

class _MissingPage extends StatelessWidget {
  const _MissingPage();

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(),
    body: EmptyView(
      icon: Icons.explore_off_outlined,
      title: 'الصفحة غير موجودة',
      action: FilledButton(onPressed: () => context.go(Routes.home), child: const Text('العودة للرئيسية')),
    ),
  );
}

// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/storage/app_storage.dart';
import '../data/auth_repository_impl.dart';
import '../data/user_mapper.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState._(this.status, this.user, this.notice);

  const AuthState.unknown() : this._(AuthStatus.unknown, null, null);

  const AuthState.authenticated(User user) : this._(AuthStatus.authenticated, user, null);

  const AuthState.unauthenticated([String? notice]) : this._(AuthStatus.unauthenticated, null, notice);

  final AuthStatus status;
  final User? user;

  /// رسالة تظهر في شاشة الدخول (مثل «انتهت الجلسة» أو «حسابك موقوف»).
  final String? notice;

  bool get isAuthenticated => status == AuthStatus.authenticated;
}

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepositoryImpl(ref.watch(apiClientProvider)));

final authControllerProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);

/// معرّف المستخدم الحالي — تعتمد عليه كل البيانات الخاصة بالموظف فتُصفَّر تلقائياً عند الخروج أو تبديل الحساب.
final currentUserIdProvider = Provider<String?>((ref) => ref.watch(authControllerProvider.select((s) => s.user?.id)));

/// مديرية الموظف (null => حساب غير مرتبط بمديرية: لا بلاغات قسم ولا خريطة مديرية).
final currentDepartmentIdProvider = Provider<int?>(
  (ref) => ref.watch(authControllerProvider.select((s) => s.user?.departmentId)),
);

class AuthController extends Notifier<AuthState> {
  static const _expired = 'انتهت الجلسة — سجّل الدخول مجدداً';

  AppStorage get _storage => ref.read(appStorageProvider);
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  @override
  AuthState build() {
    final sub = ref.read(sessionEventsProvider).unauthorized.listen((_) => sessionExpired());
    ref.onDispose(sub.cancel);
    unawaited(Future.microtask(_restore));
    return const AuthState.unknown();
  }

  /// استعادة الجلسة عند فتح التطبيق: نتحقق من الـ Token مع الخادم،
  /// وبدون إنترنت نكمل بنسخة المستخدم المحفوظة.
  Future<void> _restore() async {
    final minimumSplash = Future<void>.delayed(const Duration(milliseconds: 900));
    final token = _storage.token;
    final cached = _storage.cachedUser;
    AuthState next;

    if (token == null || token.isEmpty) {
      next = const AuthState.unauthenticated();
    } else {
      try {
        final user = await _repo.profile().timeout(const Duration(seconds: 10));
        await _storage.saveUser(userToJson(user));
        next = AuthState.authenticated(user);
      } on ApiException catch (e) {
        if (e.kind == ApiErrorKind.unauthorized) {
          await _storage.clearSession();
          next = const AuthState.unauthenticated(_expired);
        } else if (e.kind == ApiErrorKind.forbidden) {
          // الحساب أُوقف من الإدارة أو لم يعد حساب موظف
          await _storage.clearSession();
          next = AuthState.unauthenticated(e.message);
        } else {
          next = cached != null ? AuthState.authenticated(userFromJson(cached)) : const AuthState.unauthenticated();
        }
      } on TimeoutException {
        next = cached != null ? AuthState.authenticated(userFromJson(cached)) : const AuthState.unauthenticated();
      }
    }

    await minimumSplash;
    if (state.status == AuthStatus.unknown) state = next;
  }

  Future<void> login(String identifier, String password) async {
    final session = await _repo.login(identifier: identifier, password: password);
    await _storage.saveSession(session.token, userToJson(session.user));
    state = AuthState.authenticated(session.user);
  }

  Future<void> logout() async {
    try {
      await _repo.logout().timeout(const Duration(seconds: 6));
    } catch (_) {
      // حتى بدون إنترنت: نحذف الجلسة من الجهاز
    }
    await _storage.clearSession();
    state = const AuthState.unauthenticated();
  }

  /// 401 من أي طلب => الجلسة انتهت (Token حُذف، أو سُجّل الدخول من جهاز آخر).
  void sessionExpired() => _end(_expired);

  void _end(String notice) {
    if (state.status == AuthStatus.unauthenticated) return;
    unawaited(_storage.clearSession());
    state = AuthState.unauthenticated(notice);
  }

  /// تحديث بيانات الحساب من الخادم (السحب للتحديث في «حسابي»، وعند العودة للتطبيق).
  /// 403 هنا يعني أن الحساب أُوقف => خروج مع رسالة الخادم.
  Future<void> refreshProfile() async {
    try {
      await setUser(await _repo.profile());
    } on ApiException catch (e) {
      if (e.kind == ApiErrorKind.forbidden) {
        _end(e.message);
        return;
      }
      rethrow;
    }
  }

  Future<void> setUser(User user) async {
    await _storage.saveUser(userToJson(user));
    state = AuthState.authenticated(user);
  }
}

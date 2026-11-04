// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../features/reports/domain/report.dart';
import 'app_colors.dart';

/// ألوان وأيقونات حالات البلاغ وأولوياته وتصنيفاته.
extension ReportStatusStyle on ReportStatus {
  Color get color => switch (this) {
    ReportStatus.pending => AppColors.pending,
    ReportStatus.underReview => AppColors.underReview,
    ReportStatus.assigned => AppColors.assigned,
    ReportStatus.inProgress => AppColors.inProgress,
    ReportStatus.resolved => AppColors.resolved,
    ReportStatus.closed => AppColors.closed,
    ReportStatus.rejected => AppColors.rejected,
    ReportStatus.unknown => AppColors.low,
  };

  IconData get icon => switch (this) {
    ReportStatus.pending => Icons.schedule_rounded,
    ReportStatus.underReview => Icons.manage_search_rounded,
    ReportStatus.assigned => Icons.assignment_ind_outlined,
    ReportStatus.inProgress => Icons.engineering_outlined,
    ReportStatus.resolved => Icons.task_alt_rounded,
    ReportStatus.closed => Icons.lock_outline_rounded,
    ReportStatus.rejected => Icons.block_rounded,
    ReportStatus.unknown => Icons.help_outline_rounded,
  };
}

extension ReportPriorityStyle on ReportPriority {
  Color get color => switch (this) {
    ReportPriority.low => AppColors.low,
    ReportPriority.medium => AppColors.medium,
    ReportPriority.high => AppColors.high,
    ReportPriority.critical => AppColors.critical,
  };

  IconData get icon => switch (this) {
    ReportPriority.low => Icons.keyboard_double_arrow_down_rounded,
    ReportPriority.medium => Icons.drag_handle_rounded,
    ReportPriority.high => Icons.keyboard_double_arrow_up_rounded,
    ReportPriority.critical => Icons.priority_high_rounded,
  };
}

/// أيقونات التصنيفات: الخادم يرسل اسم أيقونة Material (CategorySeeder). تصنيف جديد بأيقونة غير معروفة => أيقونة عامة.
const _categoryIcons = <String, IconData>{
  'construction': Icons.construction_rounded,
  'directions_walk': Icons.directions_walk_rounded,
  'delete': Icons.delete_outline_rounded,
  'water_damage': Icons.water_damage_outlined,
  'power_off': Icons.power_off_outlined,
  'lightbulb': Icons.lightbulb_outline_rounded,
  'electrical_services': Icons.electrical_services_rounded,
  'bolt': Icons.bolt_rounded,
  'water_drop': Icons.water_drop_outlined,
  'format_color_reset': Icons.format_color_reset_outlined,
  'warning': Icons.warning_amber_rounded,
  'traffic': Icons.traffic_outlined,
  'directions_car': Icons.directions_car_outlined,
  'signpost': Icons.signpost_outlined,
  'eco': Icons.eco_outlined,
  'local_fire_department': Icons.local_fire_department_outlined,
  'waves': Icons.waves_rounded,
  'domain_disabled': Icons.domain_disabled_outlined,
  'whatshot': Icons.whatshot_outlined,
  'report': Icons.report_outlined,
  'wifi_off': Icons.wifi_off_rounded,
  'signal_cellular_off': Icons.signal_cellular_off_rounded,
  'health_and_safety': Icons.health_and_safety_outlined,
  'science': Icons.science_outlined,
};

IconData categoryIcon(String? name) => _categoryIcons[name] ?? Icons.report_problem_outlined;

Color categoryColor(String? hex) => AppColors.fromHex(hex, fallback: AppColors.green);

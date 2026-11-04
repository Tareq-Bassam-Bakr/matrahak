// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/state_views.dart';
import '../domain/lookups.dart';
import 'lookups_providers.dart';

Future<Category?> showCategoryPicker(BuildContext context, {int? selectedId}) => showModalBottomSheet<Category>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => CategoryPickerSheet(selectedId: selectedId),
);

/// اختيار تصنيف البلاغ مجمّعاً حسب المديرية مع بحث.
class CategoryPickerSheet extends ConsumerStatefulWidget {
  const CategoryPickerSheet({super.key, this.selectedId});

  final int? selectedId;

  @override
  ConsumerState<CategoryPickerSheet> createState() => _CategoryPickerSheetState();
}

class _CategoryPickerSheetState extends ConsumerState<CategoryPickerSheet> {
  String _query = '';

  bool _matches(Category c) {
    if (_query.isEmpty) return true;
    final q = _query.trim();
    return c.name.contains(q) ||
        (c.departmentName ?? '').contains(q) ||
        (c.nameEn ?? '').toLowerCase().contains(q.toLowerCase());
  }

  @override
  Widget build(BuildContext context) {
    final grouped = ref.watch(categoriesByDepartmentProvider);
    final theme = Theme.of(context);

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      builder: (context, controller) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('اختر نوع المشكلة', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(
                  'إن لم تجد التصنيف المناسب أغلق النافذة — سيحدده الذكاء الاصطناعي من وصفك.',
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 10),
                TextField(
                  decoration: const InputDecoration(
                    hintText: 'ابحث: حفرة، كهرباء، مياه...',
                    prefixIcon: Icon(Icons.search_rounded),
                    isDense: true,
                  ),
                  onChanged: (v) => setState(() => _query = v),
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncValueView<Map<String, List<Category>>>(
              value: grouped,
              onRetry: () => ref.invalidate(categoriesProvider),
              data: (groups) {
                final entries = [
                  for (final e in groups.entries)
                    if (e.value.any(_matches)) MapEntry(e.key, e.value.where(_matches).toList()),
                ];
                if (entries.isEmpty) {
                  return const EmptyView(icon: Icons.search_off_rounded, title: 'لا يوجد تصنيف مطابق');
                }
                return ListView(
                  controller: controller,
                  padding: const EdgeInsets.only(bottom: 24),
                  children: [
                    for (final group in entries) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
                        child: Text(
                          group.key,
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: theme.colorScheme.primary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      for (final c in group.value)
                        ListTile(
                          leading: CategoryAvatar(icon: c.icon, color: c.color, size: 40),
                          title: Text(c.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: c.slaHours == null
                              ? null
                              : Text('المهلة المعتادة للمعالجة: ${formatHours(c.slaHours!.toDouble())}'),
                          trailing: c.id == widget.selectedId
                              ? Icon(Icons.check_circle_rounded, color: theme.colorScheme.primary)
                              : null,
                          onTap: () => Navigator.pop(context, c),
                        ),
                    ],
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

class RatingInput {
  const RatingInput(this.rating, this.feedback);

  final int rating;
  final String? feedback;
}

Future<RatingInput?> showRatingSheet(BuildContext context) => showModalBottomSheet<RatingInput>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => const _RatingSheet(),
);

class _RatingSheet extends StatefulWidget {
  const _RatingSheet();

  @override
  State<_RatingSheet> createState() => _RatingSheetState();
}

class _RatingSheetState extends State<_RatingSheet> {
  int _rating = 0;
  final _feedback = TextEditingController();

  static const _labels = ['', 'سيئة جداً', 'سيئة', 'مقبولة', 'جيدة', 'ممتازة'];

  @override
  void dispose() {
    _feedback.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(context).bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'قيّم معالجة بلاغك',
            textAlign: TextAlign.center,
            style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            'تقييمك يصل للجهة المختصة ويساعد في تحسين الخدمة.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
          const SizedBox(height: 16),
          Directionality(
            // النجوم من اليسار لليمين مثل المعتاد في التقييم
            textDirection: TextDirection.ltr,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 1; i <= 5; i++)
                  IconButton(
                    iconSize: 40,
                    tooltip: _labels[i],
                    onPressed: () => setState(() => _rating = i),
                    icon: Icon(i <= _rating ? Icons.star_rounded : Icons.star_outline_rounded, color: AppColors.gold),
                  ),
              ],
            ),
          ),
          Text(
            _labels[_rating],
            textAlign: TextAlign.center,
            style: theme.textTheme.titleSmall?.copyWith(color: AppColors.gold, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _feedback,
            maxLength: 1000,
            minLines: 2,
            maxLines: 4,
            decoration: const InputDecoration(labelText: 'ملاحظاتك (اختياري)', alignLabelWithHint: true),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: _rating == 0
                ? null
                : () => Navigator.pop(
                    context,
                    RatingInput(_rating, _feedback.text.trim().isEmpty ? null : _feedback.text.trim()),
                  ),
            child: const Text('إرسال التقييم'),
          ),
        ],
      ),
    );
  }
}

// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/widgets/app_network_image.dart';
import '../../../core/widgets/feedback.dart';
import '../domain/report.dart';

class MediaViewerArgs {
  const MediaViewerArgs(this.items, [this.initialIndex = 0]);

  final List<Attachment> items;
  final int initialIndex;
}

/// عرض صور البلاغ (قبل وبعد المعالجة) بملء الشاشة مع التكبير؛ الفيديو يُفتح بمشغّل الجهاز.
class MediaViewerScreen extends StatefulWidget {
  const MediaViewerScreen({super.key, required this.args});

  final MediaViewerArgs args;

  @override
  State<MediaViewerScreen> createState() => _MediaViewerScreenState();
}

class _MediaViewerScreenState extends State<MediaViewerScreen> {
  late final _controller = PageController(initialPage: widget.args.initialIndex);
  late int _index = widget.args.initialIndex;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _openVideo(String url) async {
    final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!ok) showAppSnack('تعذّر فتح الفيديو', error: true);
  }

  @override
  Widget build(BuildContext context) {
    final items = widget.args.items;
    final current = items.isEmpty ? null : items[_index.clamp(0, items.length - 1)];
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text('${_index + 1} / ${items.length}', style: const TextStyle(color: Colors.white)),
      ),
      body: Column(
        children: [
          Expanded(
            child: PageView.builder(
              controller: _controller,
              itemCount: items.length,
              onPageChanged: (i) => setState(() => _index = i),
              itemBuilder: (context, i) {
                final item = items[i];
                if (item.isVideo) {
                  return Center(
                    child: FilledButton.icon(
                      onPressed: () => _openVideo(item.url),
                      icon: const Icon(Icons.play_arrow_rounded),
                      label: const Text('تشغيل الفيديو'),
                    ),
                  );
                }
                return InteractiveViewer(
                  minScale: 1,
                  maxScale: 5,
                  child: Center(child: AppNetworkImage(item.url, fit: BoxFit.contain)),
                );
              },
            ),
          ),
          if (current != null && current.aiTags.isNotEmpty)
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  alignment: WrapAlignment.center,
                  children: [
                    const Icon(Icons.auto_awesome_rounded, color: Colors.white70, size: 18),
                    for (final tag in current.aiTags)
                      Chip(
                        label: Text(tag),
                        visualDensity: VisualDensity.compact,
                        backgroundColor: Colors.white12,
                        labelStyle: const TextStyle(color: Colors.white),
                        side: BorderSide.none,
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

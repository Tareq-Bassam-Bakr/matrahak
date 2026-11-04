@php
    /** @var \App\Models\Report $record */
    $record = $getRecord();
    $groups = $record->attachments()->get()->groupBy(fn ($a) => $a->stage === 'after' ? 'after' : 'before');
@endphp

{{-- أنماط مضمّنة: لوحة Filament لا تحتوي كل أصناف Tailwind بدون بناء Theme مخصص --}}
<style>
    .sc-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .sc-gallery a { display: block; border-radius: 12px; overflow: hidden; border: 1px solid rgba(148, 163, 184, .35); background: rgba(148, 163, 184, .08); text-decoration: none; }
    .sc-gallery .sc-media { width: 100%; height: 130px; object-fit: cover; display: block; transition: transform .2s; }
    .sc-gallery a:hover .sc-media { transform: scale(1.04); }
    .sc-gallery .sc-file { height: 130px; display: flex; align-items: center; justify-content: center; font-size: 32px; opacity: .5; }
    .sc-gallery .sc-caption { font-size: 11px; padding: 4px 8px; opacity: .75; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sc-gallery-title { font-size: 13px; font-weight: 600; margin: 4px 0 8px; }
    .sc-gallery .sc-tags { display: flex; flex-wrap: wrap; gap: 4px; padding: 0 8px 6px; }
    .sc-gallery .sc-tag { font-size: 10px; line-height: 1.6; padding: 0 6px; border-radius: 999px; background: rgba(15, 107, 74, .12); color: #0f6b4a; }
    .sc-gallery .sc-tag.sc-warn { background: rgba(220, 38, 38, .12); color: #b91c1c; }
</style>

<div>
    @foreach (['before' => 'صور المواطن (قبل المعالجة)', 'after' => 'صور الفريق (بعد المعالجة)'] as $stage => $label)
        @if (($groups[$stage] ?? collect())->isNotEmpty())
            <div style="margin-bottom: 16px;">
                <div class="sc-gallery-title">{{ $label }}</div>
                <div class="sc-gallery">
                    @foreach ($groups[$stage] as $attachment)
                        <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener">
                            @if ($attachment->file_type === 'image')
                                <img class="sc-media" src="{{ $attachment->thumbnail_url ?? $attachment->file_url }}" alt="{{ $attachment->file_name }}" loading="lazy">
                            @elseif ($attachment->file_type === 'video')
                                <video class="sc-media" src="{{ $attachment->file_url }}" preload="metadata" muted></video>
                            @else
                                <div class="sc-file">📄</div>
                            @endif
                            <div class="sc-caption">{{ $attachment->file_name }} · {{ $attachment->file_size_formatted }}</div>
                            {{-- وسوم تحليل الصورة (خدمة الذكاء الاصطناعي): جودة الصورة ومؤشرات مثل احتمال الحريق --}}
                            @if (! empty($attachment->ai_tags))
                                <div class="sc-tags" title="تحليل الصورة بالذكاء الاصطناعي">
                                    @foreach ($attachment->ai_tags as $tag)
                                        <span @class(['sc-tag', 'sc-warn' => $attachment->ai_severity >= 7])>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

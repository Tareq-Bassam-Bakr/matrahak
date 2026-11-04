<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Filament\Resources\ReportResource\Pages;
use App\Filament\Resources\ReportResource\RelationManagers;
use App\Filament\Resources\ReportResource\ReportActions;
use App\Models\Category;
use App\Models\Report;
use App\Support\ManagerScope;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'البلاغات';

    protected static ?string $navigationLabel = 'البلاغات';

    protected static ?string $modelLabel = 'بلاغ';

    protected static ?string $pluralModelLabel = 'البلاغات';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function getNavigationBadge(): ?string
    {
        $count = ManagerScope::reports()->open()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ManagerScope::reports()->overdue()->exists() ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'البلاغات المفتوحة';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ticket_number', 'title'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Report $record */
        return [
            'الحالة' => $record->status->label(),
            'المحافظة' => $record->district?->name ?? '—',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // مدير المحافظة يرى بلاغات محافظته فقط (ويُمنع فتح غيرها حتى بالرابط المباشر)
        return ManagerScope::apply(parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'category:id,name,color,department_id', 'department:id,name', 'district:id,name',
                'activeAssignment.employee:id,name', 'latestAiClassification', 'aiCategory:id,name',
            ]))
            ->defaultSort('id', 'desc')
            ->poll('60s')
            ->striped()
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('رقم التذكرة')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (Report $record) => $record->title)
                    ->description(fn (Report $record) => $record->category?->name ?? 'بدون تصنيف — بانتظار التحليل'),
                TextColumn::make('status')->label('الحالة')->badge()->sortable(),
                TextColumn::make('priority')->label('الأولوية')->badge()->sortable(),
                TextColumn::make('department.name')->label('المديرية')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('district.name')->label('المحافظة')->toggleable()->placeholder('—'),
                TextColumn::make('activeAssignment.employee.name')->label('الموظف')->placeholder('غير مُسند')->toggleable(),
                IconColumn::make('ai_is_duplicate')
                    ->label('مكرر؟')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-duplicate')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (Report $record) => $record->ai_is_duplicate
                        ? 'تشابه '.(int) round(($record->ai_duplicate_score ?? 0) * 100).'%' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ai_confidence')
                    ->label('اقتراح AI')
                    ->formatStateUsing(fn (Report $record) => $record->ai_category_id
                        ? ($record->aiCategory?->name ?? '—').' · '.(int) round(($record->ai_confidence ?? 0) * 100).'%'
                        : '—')
                    ->color(fn (Report $record) => ReportActions::hasPendingAi($record) ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expected_resolution')
                    ->label('المهلة')
                    ->dateTime('Y-m-d H:i')
                    ->color(fn (Report $record) => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (Report $record) => $record->isOverdue() ? 'heroicon-o-clock' : null)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('upvotes_count')->label('تأييد')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('تاريخ الرفع')->since()->sortable()
                    ->tooltip(fn (Report $record) => $record->created_at?->format('Y-m-d H:i')),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(ReportStatus::class)->multiple(),
                SelectFilter::make('priority')->label('الأولوية')->options(ReportPriority::class)->multiple(),
                SelectFilter::make('department_id')->label('المديرية')->relationship('department', 'name')->searchable()->preload(),
                SelectFilter::make('district_id')->label('المحافظة')->relationship('district', 'name')->searchable()->preload(),
                SelectFilter::make('category_id')->label('التصنيف')->relationship('category', 'name')->searchable()->preload(),
                TernaryFilter::make('assigned')
                    ->label('الإسناد')
                    ->trueLabel('مُسندة')
                    ->falseLabel('غير مُسندة')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('assignments', fn ($a) => $a->where('is_active', true)),
                        false: fn (Builder $query) => $query->unassigned(),
                    ),
                Filter::make('overdue')->label('متأخرة عن المهلة')->toggle()->query(fn (Builder $query) => $query->overdue()),
                Filter::make('duplicates')->label('مكررة محتملة')->toggle()->query(fn (Builder $query) => $query->where('ai_is_duplicate', true)),
                Filter::make('created_at')
                    ->label('تاريخ الرفع')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $w, $d) => $w->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $w, $d) => $w->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if ($data['from'] ?? null) {
                            $out[] = 'من '.$data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $out[] = 'إلى '.$data['until'];
                        }

                        return $out;
                    }),
                TrashedFilter::make()->label('المحذوفة'),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
                Tables\Actions\ActionGroup::make([
                    ...ReportActions::forTable()->all(),
                    Tables\Actions\DeleteAction::make()->label('حذف'),
                    Tables\Actions\RestoreAction::make()->label('استعادة'),
                ])->tooltip('إجراءات'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkAutoAssign')
                        ->label('إسناد تلقائي للمحدد')
                        ->icon('heroicon-o-bolt')
                        ->visible(fn () => auth()->user()->can('reports.assign'))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $assigned = 0;
                            $service = app(\App\Services\Reports\AssignmentService::class);
                            foreach ($records as $record) {
                                if ($service->autoAssign($record)) {
                                    $assigned++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("تم إسناد {$assigned} من ".$records->count().' بلاغ')
                                ->body($assigned < $records->count() ? 'البلاغات المتبقية: مغلقة أو مُسندة مسبقاً أو لا يوجد موظف متاح في مديريتها' : null)
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد بلاغات')
            ->emptyStateDescription('ستظهر هنا البلاغات المرفوعة من تطبيق المواطن.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(3)->schema([
                Section::make('تفاصيل البلاغ')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ticket_number')->label('رقم التذكرة')->weight(FontWeight::Bold)->copyable(),
                        TextEntry::make('status')->label('الحالة')->badge(),
                        TextEntry::make('priority')->label('الأولوية')->badge(),
                        TextEntry::make('title')->label('العنوان')->columnSpanFull()->weight(FontWeight::SemiBold),
                        TextEntry::make('description')->label('الوصف')->columnSpanFull()->prose(),
                        TextEntry::make('category.name')->label('التصنيف')->placeholder('بدون تصنيف'),
                        TextEntry::make('department.name')->label('المديرية')->placeholder('—'),
                        TextEntry::make('district.name')->label('المحافظة')->placeholder('—'),
                        TextEntry::make('activeAssignment.employee.name')->label('الموظف المسؤول')->placeholder('غير مُسند'),
                        TextEntry::make('created_at')->label('تاريخ الرفع')->dateTime('Y-m-d H:i'),
                        TextEntry::make('expected_resolution')
                            ->label('مهلة الحل (SLA)')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—')
                            ->color(fn (Report $record) => $record->isOverdue() ? 'danger' : null)
                            ->suffix(fn (Report $record) => $record->isOverdue() ? ' — متأخر' : null),
                        TextEntry::make('started_at')->label('بدء المعالجة')->dateTime('Y-m-d H:i')->placeholder('—'),
                        TextEntry::make('resolved_at')->label('تاريخ الحل')->dateTime('Y-m-d H:i')->placeholder('—'),
                        TextEntry::make('rating.rating')
                            ->label('تقييم المواطن')
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                            ->placeholder('لم يُقيَّم'),
                        TextEntry::make('rating.feedback')->label('تعليق التقييم')->placeholder('—')->columnSpan(2),
                    ]),
                Section::make('المواطن والموقع')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('citizen.name')
                            ->label('المواطن')
                            ->formatStateUsing(fn (Report $record, $state) => $record->is_anonymous ? $state.' (بلاغ مجهول للعامة)' : $state),
                        TextEntry::make('citizen.phone')->label('الهاتف')->placeholder('—')->copyable(),
                        TextEntry::make('address')->label('العنوان')->placeholder('—'),
                        TextEntry::make('location_notes')->label('ملاحظات الموقع')->placeholder('—'),
                        TextEntry::make('coordinates')
                            ->label('الإحداثيات')
                            ->state(fn (Report $record) => number_format($record->latitude, 6).', '.number_format($record->longitude, 6))
                            ->url(fn (Report $record) => "https://www.openstreetmap.org/?mlat={$record->latitude}&mlon={$record->longitude}#map=17/{$record->latitude}/{$record->longitude}", true),
                        TextEntry::make('upvotes_count')->label('تأييد المواطنين')->numeric(),
                        ImageEntry::make('qr_code_path')
                            ->label('رمز QR للتتبع')
                            ->disk('public')
                            ->height(120)
                            ->visible(fn (Report $record) => filled($record->qr_code_path))
                            ->url(fn (Report $record) => $record->trackingUrl(), true),
                    ]),
            ]),
            Section::make('الموقع على الخريطة')
                ->collapsible()
                ->schema([
                    ViewEntry::make('map')->hiddenLabel()->view('filament.infolists.report-map'),
                ]),
            Section::make('المرفقات')
                ->collapsible()
                ->visible(fn (Report $record) => $record->attachments()->exists())
                ->schema([
                    ViewEntry::make('attachments_gallery')->hiddenLabel()->view('filament.infolists.report-attachments'),
                ]),
            Section::make('تحليل الذكاء الاصطناعي')
                ->icon('heroicon-o-sparkles')
                ->collapsible()
                ->columns(4)
                ->visible(fn (Report $record) => $record->ai_processed_at !== null || $record->ai_category_id !== null)
                ->schema([
                    TextEntry::make('aiCategory.name')->label('التصنيف المقترح')->placeholder('—'),
                    TextEntry::make('aiDepartment.name')->label('المديرية المقترحة')->placeholder('—'),
                    TextEntry::make('ai_priority')->label('الأولوية المقترحة')->badge()->placeholder('—'),
                    TextEntry::make('ai_confidence')
                        ->label('درجة الثقة')
                        ->formatStateUsing(fn ($state) => $state !== null ? (int) round($state * 100).'%' : '—'),
                    TextEntry::make('ai_summary')->label('الملخص الآلي')->columnSpanFull()->placeholder('—'),
                    // تفسير القرار (Explainability): لماذا اختار النموذج هذا التصنيف؟
                    TextEntry::make('latestAiClassification.keywords')
                        ->label('الكلمات المفتاحية المطابقة')
                        ->badge()->color('gray')
                        ->placeholder('—')
                        ->columnSpan(2),
                    TextEntry::make('ai_explanation')
                        ->label('أهم ما استند إليه النموذج')
                        ->state(fn (Report $record) => collect(data_get($record->latestAiClassification?->raw_response, 'explanation', []))
                            ->pluck('term')->filter()->take(6)->values()->all())
                        ->badge()->color('info')
                        ->placeholder('—')
                        ->columnSpan(2),
                    TextEntry::make('ai_alternatives')
                        ->label('تصنيفات بديلة محتملة')
                        ->state(fn (Report $record) => static::aiAlternatives($record))
                        ->badge()->color('gray')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    IconEntry::make('ai_is_duplicate')->label('مكرر محتمل')->boolean(),
                    TextEntry::make('duplicateOf.ticket_number')
                        ->label('البلاغ الأصلي')
                        ->placeholder('—')
                        ->url(fn (Report $record) => $record->duplicateOf ? static::getUrl('view', ['record' => $record->duplicateOf]) : null),
                    TextEntry::make('ai_duplicate_score')
                        ->label('نسبة التشابه')
                        ->formatStateUsing(fn ($state) => $state !== null ? (int) round($state * 100).'%' : '—'),
                    TextEntry::make('latestAiClassification.backend')
                        ->label('المحرك')
                        ->formatStateUsing(fn ($state, Report $record) => $state.' · '.($record->latestAiClassification?->model_version ?? ''))
                        ->placeholder('—'),
                    TextEntry::make('latestAiClassification.is_accepted')
                        ->label('قرار المراجعة')
                        ->formatStateUsing(fn ($state) => match ($state) {
                            true, 1, '1' => 'معتمد',
                            false, 0, '0' => 'مرفوض',
                            default => 'بانتظار المراجعة',
                        })
                        ->default('بانتظار المراجعة'),
                    TextEntry::make('ai_processed_at')->label('وقت التحليل')->dateTime('Y-m-d H:i')->placeholder('—'),
                ]),
        ]);
    }

    /** البدائل مع احتمالها (من نموذج خدمة Python فقط؛ درجات البديل المحلي ليست احتمالات) */
    protected static function aiAlternatives(Report $record): array
    {
        $ai = $record->latestAiClassification;
        $alternatives = collect($ai?->alternatives ?? [])->filter(fn ($a) => is_array($a) && isset($a['slug']));
        if ($alternatives->isEmpty()) {
            return [];
        }
        $names = Category::query()->whereIn('slug', $alternatives->pluck('slug'))->pluck('name', 'slug');
        $isProbability = $ai->backend !== 'laravel-keywords';

        return $alternatives->map(fn (array $a) => ($names[$a['slug']] ?? $a['slug'])
            .($isProbability && is_numeric($a['score'] ?? null) ? ' — '.(int) round($a['score'] * 100).'%' : ''))
            ->values()->all();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UpdatesRelationManager::class,
            RelationManagers\AssignmentsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
        ];
    }
}

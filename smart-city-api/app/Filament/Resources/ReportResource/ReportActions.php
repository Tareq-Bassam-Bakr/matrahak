<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\ReportAiProcessor;
use App\Services\Reports\AssignmentService;
use App\Services\Reports\ReportWorkflowService;
use Closure;
use Filament\Actions\Action as PageAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * إجراءات البلاغ في لوحة الإدارة — نفس التعريف يُستخدم في الجدول (Table Actions)
 * وفي صفحة عرض البلاغ (Header Actions)، وكلها تمر عبر نفس خدمات تطبيق الموظف
 * (ReportWorkflowService / AssignmentService) => نفس السجل الزمني والإشعارات أينما نُفّذ الإجراء.
 */
final class ReportActions
{
    /**
     * @param  class-string<PageAction|TableAction>  $class
     * @param  Closure|null  $after  يُنفَّذ بعد نجاح أي إجراء (مثلاً إعادة تحميل صفحة العرض)
     */
    public function __construct(private readonly string $class, private readonly ?Closure $after = null) {}

    public static function forTable(): self
    {
        return new self(TableAction::class);
    }

    public static function forPage(Closure $after): self
    {
        return new self(PageAction::class, $after);
    }

    /** @return list<PageAction|TableAction> */
    public function all(): array
    {
        return [
            $this->assign(),
            $this->autoAssign(),
            $this->changeStatus(),
            $this->reclassify(),
            $this->transfer(),
            $this->addNote(),
            $this->reply(),
            $this->acceptAi(),
            $this->rejectAi(),
            $this->notDuplicate(),
            $this->closeAsDuplicate(),
            $this->reanalyze(),
        ];
    }

    public function assign(): PageAction|TableAction
    {
        return $this->class::make('assign')
            ->label('إسناد لموظف')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (Report $record) => $record->isOpen() && self::user()->can('reports.assign'))
            ->modalHeading(fn (Report $record) => 'إسناد البلاغ '.$record->ticket_number)
            ->modalSubmitActionLabel('إسناد')
            ->form(fn (Report $record) => [
                Select::make('employee_id')
                    ->label('الموظف')
                    ->options(self::employeeOptions($record))
                    ->searchable()
                    ->required()
                    ->default($record->activeAssignment?->employee_id)
                    ->helperText('بجانب الاسم: محافظة الموظف وعدد بلاغاته المفتوحة. الإسناد لموظف من مديرية أخرى ينقل البلاغ لمديريته.'),
                Textarea::make('notes')->label('تعليمات للموظف (اختياري)')->rows(2)->maxLength(1000),
            ])
            ->action(function (Report $record, array $data) {
                $employee = User::query()->findOrFail($data['employee_id']);
                $this->run(
                    fn () => app(AssignmentService::class)->assign($record, $employee, self::user(), 'manual', $data['notes'] ?? null),
                    'تم إسناد البلاغ إلى '.$employee->name
                );
            });
    }

    public function autoAssign(): PageAction|TableAction
    {
        return $this->class::make('autoAssign')
            ->label('إسناد تلقائي')
            ->icon('heroicon-o-bolt')
            ->color('gray')
            ->visible(fn (Report $record) => $record->isOpen() && $record->department_id
                && ! $record->activeAssignment && self::user()->can('reports.assign'))
            ->requiresConfirmation()
            ->modalDescription('سيُسند البلاغ إلى الموظف الأقل عبئاً في المديرية المختصة.')
            ->action(function (Report $record) {
                $this->run(function () use ($record) {
                    if (! app(AssignmentService::class)->autoAssign($record)) {
                        throw ValidationException::withMessages(['report' => 'لا يوجد موظف فعّال في المديرية — أضف موظفاً أو أسند يدوياً']);
                    }
                }, 'تم الإسناد التلقائي');
            });
    }

    public function changeStatus(): PageAction|TableAction
    {
        return $this->class::make('changeStatus')
            ->label('تغيير الحالة')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Report $record) => self::user()->can('reports.update')
                && ($record->status->allowedTransitions() !== [] || self::user()->isAdmin()))
            ->modalHeading(fn (Report $record) => 'تغيير حالة البلاغ '.$record->ticket_number)
            ->form(fn (Report $record) => [
                Placeholder::make('current')
                    ->label('الحالة الحالية')
                    ->content($record->status->label()),
                Select::make('status')
                    ->label('الحالة الجديدة')
                    ->options(self::statusOptions($record))
                    ->required()
                    ->live(),
                Textarea::make('note')
                    ->label('ملاحظة تظهر للمواطن')
                    ->rows(3)
                    ->maxLength(2000)
                    ->required(fn (Get $get) => $get('status') === ReportStatus::Rejected->value)
                    ->helperText(fn (Get $get) => $get('status') === ReportStatus::Rejected->value ? 'سبب الرفض إلزامي ويُرسل للمواطن' : null),
            ])
            ->action(function (Report $record, array $data) {
                $to = ReportStatus::from($data['status']);
                $this->run(
                    fn () => app(ReportWorkflowService::class)->changeStatus(
                        $record, $to, self::user(), $data['note'] ?: 'تحديث من الإدارة', ! self::user()->isAdmin()
                    ),
                    'تم تغيير الحالة إلى «'.$to->label().'»'
                );
            });
    }

    public function reclassify(): PageAction|TableAction
    {
        return $this->class::make('reclassify')
            ->label('تصحيح التصنيف/الأولوية')
            ->icon('heroicon-o-tag')
            ->color('gray')
            ->visible(fn (Report $record) => $record->isOpen() && self::user()->can('reports.update'))
            ->form(fn (Report $record) => [
                Select::make('category_id')
                    ->label('التصنيف')
                    ->options(self::categoryOptions())
                    ->searchable()
                    ->default($record->category_id)
                    ->helperText('اختيار تصنيف يتبع مديرية أخرى يحوّل البلاغ إليها ويُسند تلقائياً.'),
                Select::make('priority')
                    ->label('الأولوية')
                    ->options(ReportPriority::class)
                    ->default($record->priority->value)
                    ->required(),
                Textarea::make('note')->label('سبب التعديل (اختياري)')->rows(2)->maxLength(1000),
            ])
            ->action(function (Report $record, array $data) {
                $category = filled($data['category_id'] ?? null) ? Category::query()->find($data['category_id']) : null;
                $this->run(
                    fn () => app(ReportWorkflowService::class)->reclassify(
                        $record,
                        self::user(),
                        $category && $category->id !== $record->category_id ? $category : null,
                        ReportPriority::from($data['priority']),
                        $data['note'] ?? null
                    ),
                    'تم تحديث التصنيف/الأولوية'
                );
            });
    }

    public function transfer(): PageAction|TableAction
    {
        return $this->class::make('transfer')
            ->label('تحويل لمديرية أخرى')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Report $record) => $record->isOpen() && self::user()->can('reports.assign'))
            ->form(fn (Report $record) => [
                Select::make('department_id')
                    ->label('المديرية')
                    ->options(Department::query()->active()->whereKeyNot($record->department_id ?? 0)->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                Select::make('category_id')
                    ->label('التصنيف ضمن المديرية الجديدة (اختياري)')
                    ->options(fn (Get $get) => $get('department_id')
                        ? Category::query()->active()->where('department_id', $get('department_id'))->orderBy('name')->pluck('name', 'id')
                        : [])
                    ->disabled(fn (Get $get) => ! $get('department_id')),
                Textarea::make('reason')->label('سبب التحويل')->required()->rows(2)->maxLength(1000),
            ])
            ->action(function (Report $record, array $data) {
                $to = Department::query()->findOrFail($data['department_id']);
                $category = filled($data['category_id'] ?? null) ? Category::query()->find($data['category_id']) : null;
                $this->run(
                    fn () => app(ReportWorkflowService::class)->transfer($record, self::user(), $to, $data['reason'], $category),
                    'تم تحويل البلاغ إلى '.$to->name
                );
            });
    }

    public function addNote(): PageAction|TableAction
    {
        return $this->class::make('addNote')
            ->label('إضافة ملاحظة')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn () => self::user()->can('reports.update'))
            ->form([
                Textarea::make('note')->label('الملاحظة')->required()->rows(3)->maxLength(2000),
                Toggle::make('is_public')->label('تظهر للمواطن (ويصله إشعار)')->default(false),
            ])
            ->action(fn (Report $record, array $data) => $this->run(
                fn () => app(ReportWorkflowService::class)->addNote($record, self::user(), $data['note'], (bool) $data['is_public']),
                'تمت إضافة الملاحظة'
            ));
    }

    public function reply(): PageAction|TableAction
    {
        return $this->class::make('reply')
            ->label('رد على المواطن')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->visible(fn (Report $record) => self::user()->can('reports.update') && $record->citizen_id)
            ->form([
                Textarea::make('message')->label('الرسالة')->required()->rows(3)->maxLength(2000),
            ])
            ->action(fn (Report $record, array $data) => $this->run(
                fn () => app(ReportWorkflowService::class)->reply($record, self::user(), $data['message']),
                'تم إرسال الرد للمواطن'
            ));
    }

    public function acceptAi(): PageAction|TableAction
    {
        return $this->class::make('acceptAi')
            ->label('اعتماد اقتراح AI')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->visible(fn (Report $record) => $record->isOpen() && self::user()->can('reports.update') && self::hasPendingAi($record))
            ->requiresConfirmation()
            ->modalHeading('اعتماد اقتراح الذكاء الاصطناعي')
            ->modalDescription(fn (Report $record) => new HtmlString(
                'التصنيف المقترح: <b>'.e($record->aiCategory?->name ?? '—').'</b> ('.e($record->aiDepartment?->name ?? '—').')'
                .'<br>الأولوية المقترحة: <b>'.e($record->ai_priority?->label() ?? '—').'</b>'
                .'<br>الثقة: <b>'.(int) round(($record->ai_confidence ?? 0) * 100).'%</b>'
            ))
            ->action(fn (Report $record) => $this->run(
                fn () => app(ReportWorkflowService::class)->acceptAiSuggestion($record, self::user()),
                'تم اعتماد اقتراح الذكاء الاصطناعي'
            ));
    }

    public function rejectAi(): PageAction|TableAction
    {
        return $this->class::make('rejectAi')
            ->label('رفض اقتراح AI')
            ->icon('heroicon-o-hand-thumb-down')
            ->color('gray')
            ->visible(fn (Report $record) => self::user()->can('reports.update') && self::hasPendingAi($record))
            ->requiresConfirmation()
            ->action(fn (Report $record) => $this->run(
                fn () => app(ReportWorkflowService::class)->rejectAiSuggestion($record, self::user()),
                'تم تسجيل رفض الاقتراح'
            ));
    }

    public function notDuplicate(): PageAction|TableAction
    {
        return $this->class::make('notDuplicate')
            ->label('ليس مكرراً')
            ->icon('heroicon-o-check-badge')
            ->color('gray')
            ->visible(fn (Report $record) => $record->ai_is_duplicate && self::user()->can('reports.update'))
            ->requiresConfirmation()
            ->action(fn (Report $record) => $this->run(
                fn () => app(ReportWorkflowService::class)->markNotDuplicate($record, self::user()),
                'تم نفي التكرار'
            ));
    }

    public function closeAsDuplicate(): PageAction|TableAction
    {
        return $this->class::make('closeAsDuplicate')
            ->label('إغلاق كمكرر')
            ->icon('heroicon-o-document-duplicate')
            ->color('danger')
            ->visible(fn (Report $record) => $record->ai_is_duplicate && $record->ai_duplicate_of && $record->isOpen()
                && self::user()->can('reports.update'))
            ->requiresConfirmation()
            ->modalDescription(fn (Report $record) => 'سيُرفض البلاغ ويُبلَّغ المواطن أن المشكلة مسجّلة مسبقاً برقم '
                .($record->duplicateOf?->ticket_number ?? '—'))
            ->action(fn (Report $record) => $this->run(
                fn () => app(ReportWorkflowService::class)->closeAsDuplicate($record, self::user()),
                'تم إغلاق البلاغ كمكرر'
            ));
    }

    public function reanalyze(): PageAction|TableAction
    {
        return $this->class::make('reanalyze')
            ->label('إعادة التحليل بالذكاء الاصطناعي')
            ->icon('heroicon-o-cpu-chip')
            ->color('gray')
            ->visible(fn () => self::user()->can('ai.manage'))
            ->requiresConfirmation()
            ->action(fn (Report $record) => $this->run(
                fn () => app(ReportAiProcessor::class)->process($record, force: true),
                'تم تحليل البلاغ — راجع قسم الذكاء الاصطناعي'
            ));
    }

    // ------------------------------------------------------------------

    private function run(Closure $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('تعذّر تنفيذ الإجراء')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title($successMessage)->success()->send();

        if ($this->after) {
            ($this->after)();
        }
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public static function hasPendingAi(Report $record): bool
    {
        if (! $record->ai_category_id) {
            return false;
        }
        $accepted = $record->latestAiClassification?->is_accepted;
        if ($accepted !== null) {
            return false; // تمت مراجعته مسبقاً
        }

        return $record->ai_category_id !== $record->category_id
            || ($record->ai_priority && $record->ai_priority->rank() > $record->priority->rank());
    }

    /** الموظفون مجمّعون حسب المديرية — مديرية البلاغ أولاً — مع عبء العمل الحالي */
    public static function employeeOptions(Report $record): array
    {
        $employees = User::query()
            ->where('user_type', 'employee')
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->with(['department:id,name', 'district:id,name'])
            ->withCount(['activeAssignments as open_load' => fn ($q) => $q->whereHas('report', fn ($r) => $r->open())])
            ->orderBy('name')
            ->get()
            ->sortBy(fn (User $u) => [
                $u->department_id === $record->department_id ? 0 : 1,
                $u->department?->name,
                $u->district_id === $record->district_id ? 0 : ($u->district_id === null ? 1 : 2),
            ]);

        $groups = [];
        foreach ($employees as $u) {
            $group = $u->department?->name ?? 'بدون مديرية';
            if ($u->department_id === $record->department_id) {
                $group = '★ '.$group.' (مديرية البلاغ)';
            }
            $place = $u->district ? trim(str_replace('محافظة', '', $u->district->name)) : 'فريق مركزي';
            $groups[$group][$u->id] = $u->name.' — '.$place.' ('.$u->open_load.')';
        }

        return $groups;
    }

    public static function statusOptions(Report $record): array
    {
        $targets = self::user()->isAdmin()
            ? array_filter(ReportStatus::cases(), fn (ReportStatus $s) => $s !== $record->status)
            : $record->status->allowedTransitions();

        $options = [];
        foreach ($targets as $s) {
            // "تم الإسناد" يتم عبر إجراء الإسناد لاختيار الموظف
            if ($s === ReportStatus::Assigned) {
                continue;
            }
            $options[$s->value] = $s->label();
        }

        return $options;
    }

    public static function categoryOptions(): array
    {
        $groups = [];
        foreach (Category::query()->active()->with('department:id,name')->orderBy('name')->get() as $c) {
            $groups[$c->department?->name ?? 'عام'][$c->id] = $c->name;
        }
        ksort($groups);

        return $groups;
    }
}

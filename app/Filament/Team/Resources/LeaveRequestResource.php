<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\LeaveRequestResource\Pages;
use App\Filament\Team\Resources\LeaveRequestResource\RelationManagers;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('employee_id')
                    ->default(fn() => Auth::user()->employee->id),
                Forms\Components\Select::make('type')
                    ->options([
                        'sick' => 'Sick Leave',
                        'vacation' => 'Vacation',
                        'unpaid' => 'Unpaid Leave',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->minDate(now()),
                Forms\Components\DatePicker::make('end_date')
                    ->required()
                    ->minDate(fn(Forms\Get $get) => $get('start_date')),
                Forms\Components\Textarea::make('reason')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->searchable()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('type')
                //     ->options([
                //         'sick' => 'Sick Leave',
                //         'vacation' => 'Vacation',
                //         'unpaid' => 'Unpaid Leave',
                //     ]),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\Filter::make('my_requests')
                    ->label('My Requests')
                    ->query(function (Builder $query) {
                        $employee = auth()->user()?->employee;
                        return $employee
                            ? $query->where('employee_id', $employee->id)
                            : $query->whereNull('id'); // No results if not an employee
                    })
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function (LeaveRequest $record): bool {
                        $employee = auth()->user()?->employee;
                        return $record->status === 'pending'
                            && $employee
                            && $record->employee_id === $employee->id;
                    }),
                Tables\Actions\Action::make('approve')
                    ->action(function (LeaveRequest $record) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation()
                    // ->visible(fn() => auth()->user()->can('approve_leave_requests'))
                    ->color('success'),
                Tables\Actions\Action::make('reject')
                    ->action(function (LeaveRequest $record) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation()
                    // ->visible(fn() => auth()->user()->can('approve_leave_requests'))
                    ->color('danger'),
            ]);
    }




    public static function getRelations(): array
    {
        return [];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}

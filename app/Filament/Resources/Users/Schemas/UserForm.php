<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\GovernmentDocumentsRepeater;
use App\Filament\Support\PcSpecificationsRepeater;
use App\Filament\Support\TimeSelect;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('display_name'),
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('first_name'),
                        TextInput::make('last_name'),
                        TextInput::make('middle_name'),
                        TextInput::make('suffix_name'),
                        Select::make('sex')
                            ->options(['male' => 'Male', 'female' => 'Female']),
                        DatePicker::make('birthday'),
                        Select::make('civil_status')
                            ->options([
                                'single' => 'Single',
                                'married' => 'Married',
                                'widowed' => 'Widowed',
                                'divorced' => 'Divorced',
                            ]),
                        TextInput::make('phone')
                            ->tel(),
                        Textarea::make('permanent_address')
                            ->columnSpanFull(),
                        KeyValue::make('emergency_contact')
                            ->keyLabel('name')
                            ->keyPlaceholder('eg. Donila Artego')
                            ->valueLabel('phone number')
                            ->valuePlaceholder('093569143420')
                            ->addActionLabel('Add contact')
                            ->columns(2)
                            ->columnSpan(2),
                    ]),

                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('photo')
                            ->avatar()
                            ->image()
                            ->disk('public')
                            ->directory('avatar')
                            ->visibility('public')
                            ->imageEditor()
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('personal_email')
                            ->email()
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Leave blank to keep the current password.'),
                        Select::make('status')
                            ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                            ->required(),
                        Toggle::make('active'),
                    ]),

                Section::make('Employment')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('ledDepartments')
                            ->label('Team leader of')
                            ->relationship('ledDepartments', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Leading a department makes this employee a team leader who can approve that team\'s requests. No separate role needed.')
                            ->visible(fn (): bool => auth()->user()?->hasAnyRole(['superadmin', 'super_admin', 'hr']) ?? false),
                        TextInput::make('job_title'),
                        TextInput::make('employment_status'),
                        Select::make('manager_id')
                            ->label('Reports to (manager)')
                            ->options(fn (?User $record): array => User::query()
                                ->active()
                                ->when($record, fn ($query) => $query
                                    ->whereKeyNot($record->id)
                                    ->whereNotIn('id', $record->descendantIds()))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('No manager (top of org chart)')
                            ->helperText('Who this employee reports to, for the org chart. Leave blank for the company head.'),
                        Toggle::make('is_org_head')
                            ->label('Top of org chart (company head / CEO)')
                            ->helperText('Marks this person as the root of the org chart.'),
                        TextInput::make('bio_metric_id')
                            ->numeric(),
                        DatePicker::make('date_hired'),
                        DatePicker::make('regular_date'),
                        Textarea::make('job_description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Government IDs')
                    ->columns(1)
                    ->schema([
                        TextInput::make('sss')
                            ->label('SSS'),
                        TextInput::make('phic')
                            ->label('PhilHealth'),
                        TextInput::make('hdmf_tin')
                            ->label('HDMF / TIN'),
                        GovernmentDocumentsRepeater::make()
                            ->helperText('Links to the digital copies (Google Drive, OneDrive, etc.). Add a row per document.'),
                    ]),

                Section::make('PC Specifications')
                    ->description('The employee\'s workstation hardware. The employee can also edit this from their own profile.')
                    ->columns(1)
                    ->schema([
                        PcSpecificationsRepeater::make(),
                    ]),

                Section::make('Leave Balances & Schedule')
                    ->description('Stored in the related user_data record.')
                    ->relationship('userData')
                    ->columns(2)
                    ->schema([
                        TextInput::make('vacation_leave')
                            ->numeric()
                            ->default(0),
                        TextInput::make('sick_leave')
                            ->numeric()
                            ->default(0),
                        TextInput::make('emergency_leave')
                            ->numeric()
                            ->default(0),
                        TextInput::make('bereavement_leave')
                            ->numeric()
                            ->default(0),
                        TextInput::make('maternity_leave')
                            ->numeric()
                            ->default(0),
                        TextInput::make('paternity_leave')
                            ->numeric()
                            ->default(0),
                        TimeSelect::make('time_in', '10:00'),
                        TimeSelect::make('time_out', '18:00'),
                    ]),

                Section::make('Roles & Access')
                    ->description('Controls what this employee can manage in the panel.')
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Assign one or more roles. Only super-admins can change this.'),
                    ]),
            ]);
    }
}

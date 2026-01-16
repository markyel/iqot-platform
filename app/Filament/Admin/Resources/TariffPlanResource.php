<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TariffPlanResource\Pages;
use App\Filament\Admin\Resources\TariffPlanResource\RelationManagers;
use App\Models\TariffPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TariffPlanResource extends Resource
{
    protected static ?string $model = TariffPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Тарифные планы';

    protected static ?string $modelLabel = 'Тарифный план';

    protected static ?string $pluralModelLabel = 'Тарифные планы';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Код тарифа')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Уникальный код тарифа (например: start, basic, extended)')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Стоимость')
                    ->schema([
                        Forms\Components\TextInput::make('monthly_price')
                            ->label('Ежемесячная плата (₽)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Укажите 0 для бесплатного тарифа')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('price_per_item_over_limit')
                            ->label('Цена за позицию сверх лимита (₽)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('price_per_report_over_limit')
                            ->label('Цена за отчет сверх лимита (₽)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Лимиты')
                    ->schema([
                        Forms\Components\TextInput::make('items_limit')
                            ->label('Лимит позиций в месяц')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Оставьте пустым для безлимита')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('reports_limit')
                            ->label('Лимит отчетов в месяц')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Оставьте пустым для безлимита')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Дополнительный функционал')
                    ->schema([
                        Forms\Components\KeyValue::make('features')
                            ->label('Функции')
                            ->helperText('Дополнительные функции тарифа (для будущего использования)')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_price')
                    ->label('Цена/мес')
                    ->money('RUB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_limit')
                    ->label('Лимит позиций')
                    ->default('∞')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reports_limit')
                    ->label('Лимит отчетов')
                    ->default('∞')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                Tables\Columns\TextColumn::make('activeSubscriptions_count')
                    ->label('Подписчиков')
                    ->counts('activeSubscriptions')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активные')
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTariffPlans::route('/'),
            'create' => Pages\CreateTariffPlan::route('/create'),
            'edit' => Pages\EditTariffPlan::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Models\Product;
use Filament\Tables\Filters\Filter;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('unique_code')
                    ->required()
                    ->readonly()
                    ->default(Str::uuid()->toString())
                    ->columnSpan('full'),

                Select::make('customer_id')
                    ->live()
                    ->relationship(name: 'customer', titleAttribute: 'full_name')
                    ->searchable()
                    ->required()
                    ->preload(),

                Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'Pendiente',
                        'rejected' => 'Rechazada',
                        'finished' => 'Finalizada',
                    ])
                    ->native(false)
                    ->default('pending'),

                Repeater::make('orderProducts')
                    ->live()
                    ->hidden(fn (Get $get): bool => !$get('customer_id'))
                    ->label('Products')
                    ->relationship()
                    ->columns([
                        'sm' => 8,
                    ])
                    ->schema([
                        Select::make('product_id')
                            ->live()
                            ->columnSpan([
                                'sm' => 2,
                            ])
                            ->relationship(
                                'product',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('customer_id', $get('../../customer_id')),
                            )
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('price', Product::where('id', $state)->pluck('price')->first()))
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                $set('total', $get('quantity') * $get('price'));
                            })
                            ->required(),
                        TextInput::make('price')
                            ->columnSpan([
                                'sm' => 2,
                            ])
                            ->label('Precio unitario')
                            ->disabled(),
                        TextInput::make('quantity')
                            ->columnSpan([
                                'sm' => 2,
                            ])
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                $set('total', $get('quantity') * $get('price'));
                            }),
                        TextInput::make('total')
                            ->columnSpan([
                                'sm' => 2,
                            ])
                            ->disabled()
                            ->numeric(),
                        // ...
                    ])
                    ->columnSpan('full'),

                TextInput::make('total_price')
                    ->live()
                    ->hidden(fn (Get $get): bool => !$get('customer_id'))
                    ->required()
                    ->numeric()
                    ->columnSpan('full'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unique_code')
                    ->searchable(),
                TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'rejected' => 'gray',
                        'finished' => 'success',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('pending')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'pending')),

                Filter::make('rejected')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'rejected')),

                Filter::make('finished')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'finished')),

            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

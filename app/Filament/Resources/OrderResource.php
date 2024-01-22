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
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ViewColumn;

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
                    ->minItems(1)
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
                                if (is_numeric($get('quantity')) && is_numeric($get('price'))) {
                                    $set('total', $get('quantity') * $get('price'));
                                }
                            })
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                self::updateTotalPrice($get, $set);
                            }),
                        TextInput::make('total')
                            ->columnSpan([
                                'sm' => 2,
                            ])
                            ->disabled()
                            ->numeric(),
                    ])
                    ->columnSpan('full'),

                TextInput::make('calculated_price')
                    ->label('Precio Calculado')
                    ->disabled()
                    ->live()
                    ->hidden(fn (Get $get): bool => !$get('customer_id'))
                    ->numeric()
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
                TextColumn::make('index')
                    ->label('N°')
                    ->rowIndex(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unique_code')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('total_price')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->sortable(),
                SelectColumn::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                        'finished' => 'Finished',
                    ])
                    ->selectablePlaceholder(false)
                    ->searchable(),


                ViewColumn::make('products')->view('tables.columns.products'),


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
                    ->query(fn (Builder $query): Builder => $query->where('status', 'pending'))
                    ->toggle(),

                Filter::make('rejected')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'rejected'))
                    ->toggle(),

                Filter::make('finished')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'finished'))
                    ->toggle(),

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

    public static function updateTotalPrice(Get $get, Set $set): void
    {
        $selectedProducts = collect($get('../../orderProducts'));
        $total = 0;
        foreach ($selectedProducts as $selectedProduct) {
            $total += $selectedProduct['total'];
        }
        $set('../../calculated_price', $total);
        $set('../../total_price', $total);
    }
}

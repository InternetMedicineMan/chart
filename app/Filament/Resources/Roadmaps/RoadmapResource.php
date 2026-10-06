<?php

namespace App\Filament\Resources\Roadmaps;

use App\Filament\Resources\Roadmaps\Pages\CreateRoadmap;
use App\Filament\Resources\Roadmaps\Pages\EditRoadmap;
use App\Filament\Resources\Roadmaps\Pages\ListRoadmaps;
use App\Filament\Resources\Roadmaps\Schemas\RoadmapForm;
use App\Filament\Resources\Roadmaps\Tables\RoadmapsTable;
use App\Models\Roadmap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoadmapResource extends Resource
{
    protected static ?string $model = Roadmap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return RoadmapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoadmapsTable::configure($table);
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
            'index' => ListRoadmaps::route('/'),
            'create' => CreateRoadmap::route('/create'),
            'edit' => EditRoadmap::route('/{record}/edit'),
        ];
    }
}

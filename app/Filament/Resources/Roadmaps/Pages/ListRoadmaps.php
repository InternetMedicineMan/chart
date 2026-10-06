<?php

namespace App\Filament\Resources\Roadmaps\Pages;

use App\Filament\Resources\Roadmaps\RoadmapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoadmaps extends ListRecords
{
    protected static string $resource = RoadmapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

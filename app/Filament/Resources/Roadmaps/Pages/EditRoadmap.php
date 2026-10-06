<?php

namespace App\Filament\Resources\Roadmaps\Pages;

use App\Filament\Resources\Roadmaps\RoadmapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoadmap extends EditRecord
{
    protected static string $resource = RoadmapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

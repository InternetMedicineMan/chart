<?php

namespace App\Filament\Resources\Roadmaps\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoadmapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feature Request Details')
                    ->description('Manage the feature request information')
                    ->schema([
                        Hidden::make('user_id')
                            ->default(fn () => auth()->id())
                            ->dehydrated()
                            ->required(),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A brief and clear title for the feature request')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->helperText('Detailed description of what this feature should do')
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ])
                            ->default('pending')
                            ->required()
                            ->helperText('Current status of this feature request')
                            ->columnSpan(1),

                        TextInput::make('votes_count')
                            ->label('Total Votes')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Vote count is automatically calculated based on user votes')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}

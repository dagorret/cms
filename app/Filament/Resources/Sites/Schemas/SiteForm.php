<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('short_name')
                    ->required(),
                TextInput::make('long_name')
                    ->required(),
                TextInput::make('slogan'),
                TextInput::make('meta_description'),
                TextInput::make('domain')
                    ->required(),
                TextInput::make('subdir'),
            ]);
    }
}

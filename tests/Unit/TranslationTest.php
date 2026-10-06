<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    public function test_translation_strings(): void
    {
        $localeDefault = 'en';
        $localeEs = 'es';

        Lang::addLines([
            'pages.home.hero.title' => 'This is hero title',
        ], $localeDefault);

        Lang::addLines([
            'pages.home.hero.title' => 'This is hero title in spanish',
        ], $localeEs);

        App::setLocale($localeDefault);

        // Test custom hero title
        $this->assertEquals('This is hero title', __('pages.home.hero.title'));
        // Test existing translation from json file
        $this->assertEquals('Social Authorization', __('Social Auth'));

        App::setLocale($localeEs);

        $this->assertEquals('This is hero title in spanish', __('pages.home.hero.title'));
        // It will fallback to default translation (or translation key) if the translation is not found
        $this->assertEquals('Social Auth', __('Social Auth'));
    }
}

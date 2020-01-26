<?php

declare(strict_types=1);

namespace Datashaman\Tongs\Plugins\Tests;

use Datashaman\Tongs\Tongs;
use Datashaman\Tongs\Plugins\MarkdownPlugin;
use Datashaman\Tongs\Plugins\PermalinksPlugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PermalinksPluginTest extends TestCase
{
    public function testHandle()
    {
        $plugin = new PermalinksPlugin(
            [
                'pattern' => ':date-:title',
            ]
        );
        $tongs = new Tongs($this->fixture('basic'));
        $tongs->use(new MarkdownPlugin());
        $tongs->use($plugin);
        $files = $tongs->build();

        $this->assertDirEquals($this->fixture('basic/expected'), $this->fixture('basic/build'));
    }

    protected function assertFiles(string $expected, Collection $actual)
    {
        $expected = json_decode(File::get($expected), true);
        $this->assertEquals($expected, $actual->all());
    }
}

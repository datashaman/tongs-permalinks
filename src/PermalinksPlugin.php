<?php

declare(strict_types=1);

namespace Datashaman\Tongs\Plugins;

use Datashaman\Tongs\Tongs;
use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PermalinksPlugin extends Plugin
{
    /**
     * @var array
     */
    protected $linksets = [];

    /**
     * @var array
     */
    protected $defaultLinkset;

    /**
     * @var array
     */
    protected $dupes;

    public function __construct(Tongs $tongs, $options = null)
    {
        $options = $this->normalize($options);

        parent::__construct($tongs, $options);

        $this->linksets = collect($options['linksets']);

        $this->defaultLinkset = $this->linksets
            ->first(
                function ($ls) {
                    return (bool) Arr::get($ls, 'isDefault');
                }
            );

        if (is_null($this->defaultLinkset)) {
            $this->defaultLinkset = $options;
        }

        $this->dupes = [];
    }

    public function handle(Collection $files, callable $next): Collection
    {
        $files = $files
            ->mapWithKeys(
                function ($file, $path) use (&$files) {
                    if (!Str::endsWith($path, '.html')) {
                        return [
                            $path => $file,
                        ];
                    }

                    if (Arr::get($file, 'permalink') === false) {
                        return [
                            $path => $file,
                        ];
                    }

                    $linkset = array_merge(
                        $this->findLinkset($file),
                        $this->defaultLinkset
                    );

                    $ppath = $this->replace(
                        $linkset['pattern'],
                        $file,
                        $linkset
                    ) ?: $this->resolve($path);

                    $fam = null;

                    switch ($linkset['relative']) {
                    case true:
                        $fam = $this->family($path, $files->all());
                        break;
                    case 'folder':
                        $fam = $this->folder($path, $files->all());
                        break;
                    }

                    if (
                        Arr::has($file, 'permalink')
                        && $file['permalink'] !== false
                    ) {
                        $ppath = $file['permalink'];
                    }

                    $out = $this->makeUnique()($ppath, $files, $path, $this->options);

                    $moved = [];

                    if ($fam) {
                        foreach ($fam as $key => $famData) {
                            if (Arr::has($fam, $key)) {
                                $rel = implode(DIRECTORY_SEPARATOR, [$ppath, $key]);
                                $this->dupes[$rel] = $famData;
                                $moved[$key] = $rel;
                            }
                        }
                    }

                    $file['path'] = $ppath === '.'
                        ? ''
                        : str_replace('\\', '/', $ppath);

                    $file = $this->relink($file, $moved);

                    return [
                        $out => $file,
                    ];
                }
            );

        return $next($files);
    }

    protected function normalize($options): array
    {
        if (is_string($options)) {
            $options = [
                'pattern' => $options,
            ];
        }

        $options = $options ?: [];

        $options['date'] = $options['date'] ?? 'Y/m/d';

        if (!Arr::has($options, 'relative')) {
            $options['relative'] = true;
        }

        $options['linksets'] = $options['linksets'] ?? [];

        return $options;
    }

    protected function findLinkset(array $file)
    {
        $set = $this->linksets
            ->first(
                function ($ls) use ($file) {
                    return collect($ls['match'])
                        ->keys()
                        ->reduce(
                            function ($sofar, $key) use ($file, $ls) {
                                if (!$sofar) {
                                    return $sofar;
                                }

                                if (Arr::get($file, $key) === $ls['match'][$key]) {
                                    return true;
                                }

                                if (Arr::get($file, $key) && is_array($file[$key])) {
                                    return in_array($ls['match'][$key], $file[$key]);
                                }

                                return false;
                            },
                            true
                        );
                }
            );

        return $set ?: $this->defaultLinkset;
    }

    protected function replace(
        string $pattern,
        array $data,
        array $options
    ) {
        if (!$pattern) {
            return null;
        }

        $keys = $this->params($pattern);
        $ret = [];

        for($i = 0, $key = null; $key = Arr::get($keys, $i++); ) {
            $val = Arr::get($data, $key);
            if (!$val) {
                return null;
            }
            if ($val instanceof DateTime) {
                $ret[":$key"] = $val->format($options['date']);
            } else {
                $ret[":$key"] = Str::slug((string) $val);
            }
        }

        return strtr($pattern, $ret);
    }

    protected function params(string $pattern): array
    {
        preg_match_all('/:(\w+)/', $pattern, $matches);;

        return $matches[1];
    }

    protected function resolve(string $str): string
    {
        $name = File::name($str);
        $ret = File::dirname($str);

        if ($name !== 'index') {
            $ret = preg_replace(
                '/\\\/',
                '/',
                implode(DIRECTORY_SEPARATOR, [$ret, $name])
            );
        }

        return $ret === '.' ? '/' : $ret;
    }

    protected function family(
        string $file,
        array $files
    ): array {
        $ret = [];
        $dir = File::dirname($file);

        if ($dir === '.') {
            $dir = '';
        }

        foreach ($files as $key => $data) {
            if ($key === $file) {
                continue;
            }
            if (!Str::startsWith($key, $dir)) {
                continue;
            }
            if (Str::endsWith($key, '.html')) {
                continue;
            }

            $rel = substr($key, strlen($dir));
            $ret[$rel] = $files[$key];
        }

        return $ret;
    }

    protected function makeUnique(): callable
    {
        $unique = Arr::get($this->options, 'unique');

        return is_callable($unique)
            ? $unique
            : [$this, 'defaultUniquePath'];
    }

    public function defaultUniquePath(
        $targetPath,
        $filesObj,
        $filename,
        $opts
    ) {
        $indexFile = Arr::get($opts, 'indexFile', 'index.html');
        $target = null;
        $counter = 0;
        $postfix = '';

        do {
            $target = "${targetPath}{$postfix}{$indexFile}";

            if (Arr::get($this->options, 'duplicatesFail') && $files[$target]) {
                throw new Exception("Permalinks: Clash with another target file {$target}");
            }

            $postfix = "-{++$counter}";
        } while (Arr::get($this->options, 'unique') && $files[$target]);

        return ltrim($target, '/');
    }

    protected function relink(
        array $file,
        array $moved
    ): array {
        $content = $file['contents'];
        collect($moved)
            ->keys()
            ->each(
                function ($to) use (&$content, $moved) {
                    $from = $moved[$to];
                    $content = str_replace($from, $to, $content);
                }
            );
        $file['contents'] = $content;

        return $file;
    }
}

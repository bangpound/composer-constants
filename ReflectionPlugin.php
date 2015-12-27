<?php

namespace Bangpound\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ReflectionPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
    }
}

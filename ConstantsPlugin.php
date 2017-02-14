<?php

namespace Bangpound\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Webmozart\PathUtil\Path;

class ConstantsPlugin implements PluginInterface, EventSubscriberInterface
{
    const COMPOSER_AUTOLOADER_BASE = 'ComposerAutoloaderInit';

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var string
     */
    protected $constantPrefix = 'COMPOSER_';

    /**
     * @var bool
     */
    private $runPostAutoloadDump = true;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
          ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoloadDump',
        );
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $extra = $this->composer->getPackage()->getExtra();
        if (isset($extra['composer-constant-prefix'])) {
            $this->constantPrefix = $extra['composer-constant-prefix'];
        }
    }

    public function postAutoloadDump(Event $event)
    {
        // This method is called twice. Run it only once.
        if (!$this->runPostAutoloadDump) {
            return;
        }

        $this->runPostAutoloadDump = false;

        $config = $this->composer->getConfig();
        $suffix = $config->get('autoloader-suffix');
        $vendorDir = $config->get('vendor-dir');
        $binDir = $config->get('bin-dir');
        $autoloadFile = $vendorDir.'/autoload.php';

        if (!file_exists($autoloadFile)) {
            throw new \RuntimeException(sprintf(
              'Could not adjust autoloader: The file %s was not found.',
              $autoloadFile
            ));
        }

        if (!$suffix && !$config->get('autoloader-suffix') && is_readable($autoloadFile)) {
            $content = file_get_contents($vendorDir.'/autoload.php');
            if (preg_match('{'.self::COMPOSER_AUTOLOADER_BASE.'([^:\s]+)::}', $content, $match)) {
                $suffix = $match[1];
            }
        }

        $contents = file_get_contents($autoloadFile);
        $constant = '';

        $values = array(
          'AUTOLOAD_CLASS' => var_export(self::COMPOSER_AUTOLOADER_BASE.$suffix, true),
          'DEV' => var_export($event->isDevMode(), true),
        );

        foreach ($values as $key => $value) {
            $this->io->write('<info>Generating '.$this->constantPrefix.$key.' constant</info>');
            $constant .= "if (!defined('{$this->constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$this->constantPrefix}{$key}', %s);\n", $value);
            $constant .= "}\n\n";
        }

        $values = array_map(function ($value) {
            return var_export($value, true);
        }, array(
          'BASE_DIR' => Path::makeRelative(getcwd(), $vendorDir),
          'BIN_DIR' => Path::makeRelative($binDir, $vendorDir),
          'FILE' => Path::makeRelative(realpath(Factory::getComposerFile()), $vendorDir),
        ));

        foreach ($values as $key => $value) {
            $this->io->write('<info>Generating '.$this->constantPrefix.$key.' constant</info>');
            $constant .= "if (!defined('{$this->constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$this->constantPrefix}{$key}', realpath(__DIR__ . DIRECTORY_SEPARATOR . %s));\n", $value);
            $constant .= "}\n\n";
        }

        $values = array(
          'VENDOR_DIR' => $vendorDir,
        );

        foreach ($values as $key => $value) {
            $this->io->write('<info>Generating '.$this->constantPrefix.$key.' constant</info>');
            $constant .= "if (!defined('{$this->constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$this->constantPrefix}{$key}', realpath(__DIR__));\n");
            $constant .= "}\n\n";
        }

        // Regex modifiers:
        // "m": \s matches newlines
        // "D": $ matches at EOF only
        // Translation: insert before the last "return" in the file
        $contents = preg_replace('/\n(?=return [^;]+;\s*$)/mD', "\n".$constant, $contents);

        file_put_contents($autoloadFile, $contents);
    }
}

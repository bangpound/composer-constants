<?php

namespace Bangpound\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Filesystem\Path;

class ConstantsPlugin implements PluginInterface, EventSubscriberInterface
{
    const COMPOSER_AUTOLOADER_BASE = 'ComposerAutoloaderInit';

    /**
     * @var bool
     */
    private $runPostAutoloadDump = true;

    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoloadDump',
        );
    }

    /**
     * @param Event $event
     * @return void
     */
    public function postAutoloadDump(Event $event)
    {
        // This method is called twice. Run it only once.
        if (!$this->runPostAutoloadDump) {
            return;
        }

        $this->runPostAutoloadDump = false;

        $config = $event->getComposer()->getConfig();
        $extra = $event->getComposer()->getPackage()->getExtra();

        $io = $event->getIO();

        $constantPrefix = 'COMPOSER_';
        if (isset($extra['composer-constant-prefix'])) {
            $constantPrefix = $extra['composer-constant-prefix'];
        }

        $suffix = $config->get('autoloader-suffix');
        $vendorDir = $config->get('vendor-dir');
        $binDir = $config->get('bin-dir');
        $autoloadFile = $vendorDir . '/autoload.php';

        if (!file_exists($autoloadFile)) {
            throw new \RuntimeException(sprintf(
                'Could not adjust autoloader: The file %s was not found.',
                $autoloadFile
            ));
        }

        if (!$suffix && !$config->get('autoloader-suffix') && is_readable($autoloadFile)) {
            $content = file_get_contents($vendorDir . '/autoload.php');
            if (preg_match('{' . self::COMPOSER_AUTOLOADER_BASE . '([^:\s]+)::}', $content, $match)) {
                $suffix = $match[1];
            }
        }

        $contents = file_get_contents($autoloadFile);
        $constant = '';

        $values = [
            'AUTOLOAD_CLASS' => var_export(self::COMPOSER_AUTOLOADER_BASE . $suffix, true),
        ];

        foreach ($values as $key => $value) {
            $io->write('<info>Generating ' . $constantPrefix . $key . ' constant</info>');
            $constant .= "if (!defined('{$constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$constantPrefix}{$key}', %s);\n", $value);
            $constant .= "}\n\n";
        }

        $values = array_map(function ($value) {
            return var_export($value, true);
        }, array(
            'BASE_DIR' => Path::makeRelative(\getcwd(), $vendorDir),
            'BIN_DIR' => Path::makeRelative($binDir, $vendorDir),
            'FILE' => Path::makeRelative(\realpath(Factory::getComposerFile()), $vendorDir),
        ));

        foreach ($values as $key => $value) {
            $io->write('<info>Generating ' . $constantPrefix . $key . ' constant</info>');
            $constant .= "if (!defined('{$constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$constantPrefix}{$key}', realpath(__DIR__ . DIRECTORY_SEPARATOR . %s));\n",
                $value);
            $constant .= "}\n\n";
        }

        $values = [
            'VENDOR_DIR' => $vendorDir,
        ];

        foreach ($values as $key => $value) {
            $io->write('<info>Generating ' . $constantPrefix . $key . ' constant</info>');
            $constant .= "if (!defined('{$constantPrefix}{$key}')) {\n";
            $constant .= sprintf("    define('{$constantPrefix}{$key}', realpath(__DIR__));\n");
            $constant .= "}\n\n";
        }

        // Regex modifiers:
        // "m": \s matches newlines
        // "D": $ matches at EOF only
        // Translation: insert before the last "return" in the file
        $contents = preg_replace('/\n(?=return [^;]+;\s*$)/mD', "\n" . $constant, $contents);

        file_put_contents($autoloadFile, $contents);
    }
}

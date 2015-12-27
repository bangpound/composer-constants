<?php

namespace Bangpound\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class ReflectionPlugin implements PluginInterface, EventSubscriberInterface
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

        $io = $this->io;

        $compConfig = $this->composer->getConfig();
        $suffix = $compConfig->get('autoloader-suffix');
        $vendorDir = $compConfig->get('vendor-dir');
        $binDir = $compConfig->get('bin-dir');
        $autoloadFile = $vendorDir.'/autoload.php';


        if (!file_exists($autoloadFile)) {
            throw new \RuntimeException(sprintf(
              'Could not adjust autoloader: The file %s was not found.',
              $autoloadFile
            ));
        }

        if (!$suffix && !$compConfig->get('autoloader-suffix') && is_readable($autoloadFile)) {
            $content = file_get_contents($vendorDir.'/autoload.php');
            if (preg_match('{'.self::COMPOSER_AUTOLOADER_BASE.'([^:\s]+)::}', $content, $match)) {
                $suffix = $match[1];
            }
        }

        $contents = file_get_contents($autoloadFile);
        $constant = '';

        $autoloadClass = var_export(self::COMPOSER_AUTOLOADER_BASE.$suffix, true);

        $io->write('<info>Generating '.$this->constantPrefix.'AUTOLOAD_CLASS constant</info>');
        $constant .= "if (!defined('{$this->constantPrefix}AUTOLOAD_CLASS')) {\n";
        $constant .= sprintf("    define('{$this->constantPrefix}AUTOLOAD_CLASS', %s);\n", $autoloadClass);
        $constant .= "}\n\n";

        $vendorDir = var_export($vendorDir, true);

        $io->write('<info>Generating '.$this->constantPrefix.'VENDOR_DIR constant</info>');
        $constant .= "if (!defined('{$this->constantPrefix}VENDOR_DIR')) {\n";
        $constant .= sprintf("    define('{$this->constantPrefix}VENDOR_DIR', %s);\n", $vendorDir);
        $constant .= "}\n\n";

        $binDir = var_export($binDir, true);

        $io->write('<info>Generating '.$this->constantPrefix.'BIN_DIR constant</info>');
        $constant .= "if (!defined('{$this->constantPrefix}BIN_DIR')) {\n";
        $constant .= sprintf("    define('{$this->constantPrefix}BIN_DIR', %s);\n", $binDir);
        $constant .= "}\n\n";

        $baseDir = var_export(getcwd(), true);

        $io->write('<info>Generating '.$this->constantPrefix.'BASE_DIR constant</info>');
        $constant .= "if (!defined('{$this->constantPrefix}BASE_DIR')) {\n";
        $constant .= sprintf("    define('{$this->constantPrefix}BASE_DIR', %s);\n", $baseDir);
        $constant .= "}\n\n";

        $io->write('<info>Generating '.$this->constantPrefix.'FILE constant</info>');
        $constant .= "if (!defined('{$this->constantPrefix}FILE')) {\n";
        $constant .= sprintf("    define('{$this->constantPrefix}FILE', %s);\n", var_export(realpath(Factory::getComposerFile()), true));
        $constant .= "}\n\n";

        // Regex modifiers:
        // "m": \s matches newlines
        // "D": $ matches at EOF only
        // Translation: insert before the last "return" in the file
        $contents = preg_replace('/\n(?=return [^;]+;\s*$)/mD', "\n".$constant, $contents);

        file_put_contents($autoloadFile, $contents);
    }
}

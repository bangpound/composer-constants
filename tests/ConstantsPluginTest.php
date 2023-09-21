<?php

namespace Bangpound\Composer\Tests;

use Bangpound\Composer\ConstantsPlugin;
use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function unlink;

/**
 * @coversDefaultClass \Bangpound\Composer\ConstantsPlugin
 */
class ConstantsPluginTest extends TestCase
{
    /**
     * @covers ::__construct
     * @return void
     */
    public function testConstructor()
    {
        $plugin = new ConstantsPlugin();
        $this->assertInstanceOf(ConstantsPlugin::class, $plugin);
    }

    /**
     * @covers ::getSubscribedEvents
     *
     * @return void
     */
    public function testGetSubscribedEvents()
    {
        $this->assertIsArray(ConstantsPlugin::getSubscribedEvents());
    }

    /**
     * @covers ::postAutoloadDump
     * @uses  \Bangpound\Composer\ConstantsPlugin
     *
     * @return void
     */
    public function testPostAutoloadDump()
    {
        $plugin = new ConstantsPlugin();
        $composer = new Composer();

        $baseDir = realpath(sys_get_temp_dir());

        $autoload = <<<'EOT'
<?php

return ComposerAutoloaderInit::getLoader();
EOT;
        mkdir($baseDir.'/vendor/composer', 0777, true);
        file_put_contents($baseDir.'/vendor/autoload.php', $autoload);

        $config = new Config(false, $baseDir);
        $composer->setConfig($config);

        $package = new RootPackage('test/test', 'dev-master', 'dev-master');
        $composer->setPackage($package);

        $io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        $event = new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io);
        $plugin->postAutoloadDump($event);

        $haystack = file_get_contents($baseDir.'/vendor/autoload.php');
        $this->assertStringContainsString('COMPOSER_BIN_DIR', $haystack);
        $this->assertStringContainsString('ComposerAutoloaderInit', $haystack);

        $output = $io->getOutput();
        $this->assertStringContainsString('Generating COMPOSER_AUTOLOAD_CLASS constant', $output);
        $this->assertStringContainsString('Generating COMPOSER_BASE_DIR constant', $output);
        $this->assertStringContainsString('Generating COMPOSER_BIN_DIR constant', $output);
        $this->assertStringContainsString('Generating COMPOSER_FILE constant', $output);
        $this->assertStringContainsString('Generating COMPOSER_VENDOR_DIR constant', $output);

        unlink($baseDir.'/vendor/autoload.php');
    }

    /**
     * @covers ::postAutoloadDump
     * @uses  \Bangpound\Composer\ConstantsPlugin
     *
     * @return void
     */
    public function testPostAutoloadDumpWithCustomPrefix()
    {
        $plugin = new ConstantsPlugin();
        $composer = new Composer();

        $baseDir = realpath(sys_get_temp_dir());

        $autoload = <<<'EOT'
<?php

return ComposerAutoloaderInit::getLoader();
EOT;
        mkdir($baseDir.'/vendor/composer', 0777, true);
        file_put_contents($baseDir.'/vendor/autoload.php', $autoload);

        $config = new Config(false, $baseDir);
        $composer->setConfig($config);

        $package = new RootPackage('test/test', 'dev-master', 'dev-master');
        $package->setExtra([
            'composer-constant-prefix' => 'TEST_',
        ]);
        $composer->setPackage($package);

        $io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        $event = new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io);
        $plugin->postAutoloadDump($event);

        $haystack = file_get_contents($baseDir.'/vendor/autoload.php');
        $this->assertStringContainsString('TEST_BIN_DIR', $haystack);
        $this->assertStringContainsString('ComposerAutoloaderInit', $haystack);

        $output = $io->getOutput();
        $this->assertStringContainsString('Generating TEST_AUTOLOAD_CLASS constant', $output);
        $this->assertStringContainsString('Generating TEST_BASE_DIR constant', $output);
        $this->assertStringContainsString('Generating TEST_BIN_DIR constant', $output);
        $this->assertStringContainsString('Generating TEST_FILE constant', $output);
        $this->assertStringContainsString('Generating TEST_VENDOR_DIR constant', $output);

        unlink($baseDir.'/vendor/autoload.php');
    }

    /**
     * @covers ::postAutoloadDump
     * @uses  \Bangpound\Composer\ConstantsPlugin
     *
     * @return void
     */
    public function testPostAutoloadDumpWithoutAutoloadPhp()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Could not adjust autoloader/');

        $plugin = new ConstantsPlugin();
        $composer = new Composer();

        $baseDir = realpath(sys_get_temp_dir());

        $config = new Config(false, $baseDir);
        $composer->setConfig($config);

        $package = new RootPackage('test/test', 'dev-master', 'dev-master');
        $composer->setPackage($package);

        $io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        $event = new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io);
        $plugin->postAutoloadDump($event);
    }

    /**
     * @covers ::postAutoloadDump
     * @uses  \Bangpound\Composer\ConstantsPlugin
     *
     * @return void
     */
    public function testPostAutoloadDumpWithAutoloaderSuffix()
    {
        $plugin = new ConstantsPlugin();
        $composer = new Composer();

        $baseDir = realpath(sys_get_temp_dir());

        $autoload = <<<'EOT'
<?php

return ComposerAutoloaderInit::getLoader();
EOT;
        mkdir($baseDir.'/vendor/composer', 0777, true);
        file_put_contents($baseDir.'/vendor/autoload.php', $autoload);

        $config = new Config(false, $baseDir);
        $config->merge([
            'autoloader-suffix' => 'foo',
        ]);
        $composer->setConfig($config);

        $package = new RootPackage('test/test', 'dev-master', 'dev-master');
        $package->setExtra([
            'composer-constant-prefix' => 'TEST_',
        ]);
        $composer->setPackage($package);

        $io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        $event = new Event(ScriptEvents::POST_AUTOLOAD_DUMP, $composer, $io);
        $plugin->postAutoloadDump($event);

        $haystack = file_get_contents($baseDir.'/vendor/autoload.php');
        $this->assertStringContainsString('TEST_BIN_DIR', $haystack);
        $this->assertStringContainsString('ComposerAutoloaderInit', $haystack);

        $output = $io->getOutput();
        $this->assertStringContainsString('Generating TEST_AUTOLOAD_CLASS constant', $output);
        $this->assertStringContainsString('Generating TEST_BASE_DIR constant', $output);
        $this->assertStringContainsString('Generating TEST_BIN_DIR constant', $output);
        $this->assertStringContainsString('Generating TEST_FILE constant', $output);
        $this->assertStringContainsString('Generating TEST_VENDOR_DIR constant', $output);

        unlink($baseDir.'/vendor/autoload.php');
    }
}

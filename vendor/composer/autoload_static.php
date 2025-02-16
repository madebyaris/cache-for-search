<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit195199d77fea002a01ba82388f8898dc
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Webimpress\\SafeWriter\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Webimpress\\SafeWriter\\' => 
        array (
            0 => __DIR__ . '/..' . '/webimpress/safe-writer/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Redis_For_Search' => __DIR__ . '/../..' . '/includes/class-redis-for-search.php',
        'Redis_For_Search_Admin' => __DIR__ . '/../..' . '/includes/class-redis-for-search-admin.php',
        'Redis_For_Search_Smart_Cache' => __DIR__ . '/../..' . '/includes/class-redis-for-search-smart-cache.php',
        'Webimpress\\SafeWriter\\Exception\\ChmodException' => __DIR__ . '/..' . '/webimpress/safe-writer/src/Exception/ChmodException.php',
        'Webimpress\\SafeWriter\\Exception\\ExceptionInterface' => __DIR__ . '/..' . '/webimpress/safe-writer/src/Exception/ExceptionInterface.php',
        'Webimpress\\SafeWriter\\Exception\\RenameException' => __DIR__ . '/..' . '/webimpress/safe-writer/src/Exception/RenameException.php',
        'Webimpress\\SafeWriter\\Exception\\RuntimeException' => __DIR__ . '/..' . '/webimpress/safe-writer/src/Exception/RuntimeException.php',
        'Webimpress\\SafeWriter\\Exception\\WriteContentException' => __DIR__ . '/..' . '/webimpress/safe-writer/src/Exception/WriteContentException.php',
        'Webimpress\\SafeWriter\\FileWriter' => __DIR__ . '/..' . '/webimpress/safe-writer/src/FileWriter.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit195199d77fea002a01ba82388f8898dc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit195199d77fea002a01ba82388f8898dc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit195199d77fea002a01ba82388f8898dc::$classMap;

        }, null, ClassLoader::class);
    }
}

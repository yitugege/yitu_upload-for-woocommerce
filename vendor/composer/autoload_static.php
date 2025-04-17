<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3c12b2743057b200147445bca0caa558
{
    public static $prefixLengthsPsr4 = array (
        'Y' => 
        array (
            'Yitu\\Upload\\' => 12,
        ),
        'O' => 
        array (
            'OSS\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Yitu\\Upload\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'OSS\\' => 
        array (
            0 => __DIR__ . '/..' . '/aliyuncs/oss-sdk-php/src/OSS',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3c12b2743057b200147445bca0caa558::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3c12b2743057b200147445bca0caa558::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3c12b2743057b200147445bca0caa558::$classMap;

        }, null, ClassLoader::class);
    }
}

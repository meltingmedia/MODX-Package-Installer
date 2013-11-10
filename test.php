<?php
/**
 * Test snippet to debug
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

// Dummy loader
$classes = array(
    'Installer', 'Service', 'Provider', 'Package', 'Validator'
);
foreach ($classes as $class) {
    $file = "/home/www/labo/installer/src/meltingmedia/package/{$class}.php";
    if (file_exists($file)) {
        require_once $file;
    }
}

$target = 'ECHO';

// 2.3 format
$dependencies = array(
//    'php' => '>=5.3.3',
//    'modx' => '>=2.2.10',
    'mxFormBuilder' => '>=1.0.0-rc5',
    'tagManager' => '*',
//    'Babel' => '~2.3',
//    'OvoHBS' => '>=0.4',
);

$options = array(
    'mxFormBuilder' => array(
        'provider' => '',
        'options' => array(),
    ),
    'Babel' => array(
        'provider' => 'https://extras.melting-media.com/',
        'options' => array(),
    ),
    'OvoHBS' => array(
        'provider' => 'https://extras.melting-media.com/',
        'options' => array(),
    ),
);

if (!empty($dependencies)) {
    $installer = new meltingmedia\package\Installer($modx, array(
        'debug' => true,
        'local_path' => '',
        'log_target' => $target
    ));

    $modx->log(modX::LOG_LEVEL_INFO, 'Installing dependencies...'."\n", $target);

    $result = $installer->manageDependencies($dependencies, $options);

    //$modx->log(modX::LOG_LEVEL_INFO, $result, $target);

    return $result;
}

return 'No deps given!';

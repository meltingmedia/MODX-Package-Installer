<?php
/**
 * Test snippet to debug
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$file = '/home/www/labo/installer/src/meltingmedia/package/Installer.php';
require_once $file;

$target = 'ECHO';

$deps = array(
    'package_name' => array(
        'version' => '1.0.0-pl2',
        'version_constraint' => '>=',
        'provider_url' => 'custom/local',
        'setup' => array(),
    ),
);

$dependencies = array(
    'http://rest.modx.com/extras/' => array(
        'translit' => array(
            'version_major:>=' => 2,
        ),
        'dummy' => array(
            'version_major:>=' => '2',
            'version_minor' => '',
            'version_patch' => '',
            'release' => '',
            'release_index' => '',
            // setup options
            'setup' => array(),
        ),
    ),
    'https://extras.melting-media.com/' => array(),
    'local' => array(),
);
if (!empty($dependencies)) {
    $installer = new meltingmedia\package\Installer($modx, array(
        'debug' => true,
        'local_path' => '',
        'log_target' => $target
    ));
    $modx->log(modX::LOG_LEVEL_INFO, 'Installing dependencies...'."\n", $target, '', __FILE__, __LINE__);
    $result = $installer->manageDependencies($dependencies);
    $modx->log(modX::LOG_LEVEL_INFO, $result, $target, '', __FILE__, __LINE__);

    return 'Should be done';
}

return 'No deps given!';


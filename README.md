# MODX Package Installer

> A library which tries to help installing MODX Revolution packages easily.


## Requirements

* MODX Revolution
* PHP 5.3+


## Sample usage

    <?php
    /**
     * @var modX $modx
     */
    
    require_once '/path/to/vendor/autoload.php';

    $installer = new Melting\MODX\Package\Installer($modx);
    // Syntax is similar to MODX Revolution 2.4+ dependency management: packageName => packageVersion/constraint
    $packages = array(
        // If the provider allow it, you can specify a particular version for your package
        'getResources' => '1.6.0-pl',
        // Or some basic constraint
        'formit' => '<2.2.0-pl,>2.1.0-pl',
        'getCache' => '*',
        'discuss' => '*',
        'tickets' => '*',
    );
    
    $results = $installer->installPackages($packages);
    
    // If needed, you can pass additional options to define a particular provider to search against, as well as some setup options
    $options = array(
        'discuss' => array(
            'setup_options' => array(
                'install_demodata' => true,
                'install_resource' => true,
            ),
        ),
        'tickets' => array(
            'provider' => 'https://modstore.pro/extras/',
            'provider_data' => array(
                'username' => 'Your Username on the provider',
                'api_key' => 'Your API key',
                'name' => 'An optional name to create the provider if not already found in your Revo instance'
            ),
        ),
    );
    
    $results = $installer->installPackages($packages, $options);

    // You might also install a single package
    $installed = $installer->installPackage('quip');
    
    // Defining a package version is the provider allows it
    $installed = $installer->installPackage('quip', array('package_version' => '2.2.0-pl'));



## License

Licensed under the [MIT license](LICENSE).
Copyright 2015 Melting Media <https://github.com/meltingmedia>

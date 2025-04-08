<?php

// config for PlinCode/KmlParser
return [
    /*
    |--------------------------------------------------------------------------
    | Default KML Namespace
    |--------------------------------------------------------------------------
    |
    | This value is the default namespace used for parsing KML files.
    | Usually you don't need to change this.
    |
    */
    'namespace' => 'http://www.opengis.net/kml/2.2',

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | This value determines the temporary directory used for extracting KMZ files.
    | If null, the system temp directory will be used.
    |
    */
    'temp_directory' => null,
];

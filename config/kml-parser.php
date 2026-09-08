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
    | Accepted KML Namespaces
    |--------------------------------------------------------------------------
    |
    | A document is rejected unless it declares one of these as its default
    | namespace. 2.2 is the OGC standard; the earth.google.com variants come
    | from Google Earth and older exporters and are still common in the wild.
    | XPath always runs against whichever one the document actually declares.
    |
    */
    'supported_namespaces' => [
        'http://www.opengis.net/kml/2.2',
        'http://earth.google.com/kml/2.2',
        'http://earth.google.com/kml/2.1',
        'http://earth.google.com/kml/2.0',
    ],

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

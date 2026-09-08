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
    | Where KmzExtractor::extractAllFiles() writes when the caller names no
    | destination. Each call gets its own directory underneath it. If null,
    | the system temp directory is used.
    |
    */
    'temp_directory' => null,

    /*
    |--------------------------------------------------------------------------
    | Archive Limits
    |--------------------------------------------------------------------------
    |
    | A KMZ is a ZIP, and a ZIP can declare a handful of entries that expand
    | into far more than the machine has. An archive breaching either ceiling
    | is rejected before anything is read out of it. A real KMZ is a KML plus
    | its icons, nowhere near either number. Set one to 0 to disable it.
    |
    */
    'max_archive_entries' => 5000,

    'max_uncompressed_size' => 256 * 1024 * 1024,
];

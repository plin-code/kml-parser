# Laravel KML Parser

[![Latest Version on Packagist](https://img.shields.io/packagist/v/plin-code/kml-parser.svg?style=flat-square)](https://packagist.org/packages/plin-code/kml-parser)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/plin-code/kml-parser/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/plin-code/kml-parser/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/plin-code/kml-parser/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/plin-code/kml-parser/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/plin-code/kml-parser.svg?style=flat-square)](https://packagist.org/packages/plin-code/kml-parser)

A simple Laravel package to parse KML and KMZ files, extracting geographic data in a convenient format.

## Installation

You can install the package via composer:

```bash
composer require plin-code/kml-parser
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="kml-parser-config"
```

This is the contents of the published config file:

```php
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
```

## Usage

### Basic Usage

```php
use PlinCode\KmlParser\KmlParser;

// Parse a KML file
$parser = new KmlParser();
$parser->loadFromFile('path/to/file.kml');

// Get placemarks (points of interest)
$placemarks = $parser->getPlacemarks();

// Get styles
$styles = $parser->getStyles();

// Get style maps
$styleMaps = $parser->getStyleMaps();

// Get document name and description
$name = $parser->getDocumentName();
$description = $parser->getDocumentDescription();

// Convert to GeoJSON
$geoJson = $parser->toGeoJson();
```

### Working with KMZ Files

KMZ files are ZIP archives that contain a KML file and possibly other assets like images:

```php
// Parse a KMZ file
$parser = new KmlParser();
$parser->loadFromKmz('path/to/file.kmz');

// Work with the data just like with KML
$placemarks = $parser->getPlacemarks();
```

Extract all files from a KMZ:

```php
use PlinCode\KmlParser\KmzExtractor;

$extractor = new KmzExtractor();
$files = $extractor->extractAllFiles('path/to/file.kmz', 'extraction/directory');
```

### Facade Usage

You can also use the provided facade:

```php
use PlinCode\KmlParser\Facades\KmlParser;

$placemarks = KmlParser::loadFromFile('path/to/file.kml')->getPlacemarks();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Daniele Barbaro](https://github.com/plin-code)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

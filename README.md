<p align="center">
  <img src="https://raw.githubusercontent.com/plin-code/kml-parser/main/art/banner.png" alt="Laravel KML Parser">
</p>

# Laravel KML Parser

[![Latest Version on Packagist](https://img.shields.io/packagist/v/plin-code/kml-parser.svg?style=flat-square)](https://packagist.org/packages/plin-code/kml-parser)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/plin-code/kml-parser/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/plin-code/kml-parser/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/plin-code/kml-parser/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/plin-code/kml-parser/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/plin-code/kml-parser.svg?style=flat-square)](https://packagist.org/packages/plin-code/kml-parser)

Parse KML and KMZ files in a Laravel application. Read placemarks, styles and document metadata as plain arrays, or convert the whole thing to GeoJSON.

## Requirements

PHP 8.3 or later, Laravel 12 or 13, and the `simplexml`, `libxml` and `zip` extensions.

Laravel 11 is supported by the 2.x line. It reached the end of its security window in March 2026 and the advisories open against it have no fix in the 11.x branch, so 3.x does not accept it.

## Installation

```bash
composer require plin-code/kml-parser
```

Publish the config file if you need to change anything:

```bash
php artisan vendor:publish --tag="kml-parser-config"
```

## Quick start

```php
use PlinCode\KmlParser\KmlParser;

$parser = new KmlParser();
$parser->loadFromFile('path/to/file.kml');

$placemarks = $parser->getPlacemarks();
$styles = $parser->getStyles();
$styleMaps = $parser->getStyleMaps();
$name = $parser->getDocumentName();
$description = $parser->getDocumentDescription();
$geoJson = $parser->toGeoJson();
```

A KMZ archive works the same way, `loadFromKmz()` picks the KML out of the archive for you:

```php
$parser->loadFromKmz('path/to/file.kmz');
```

You can also load from a string with `loadFromString()`.

## Supported KML elements

KML is a large format and this package covers a subset of it. What that subset is:

| Element | Supported | Notes |
|---|---|---|
| `Document` | yes | required, `name` and `description` are read |
| `Placemark` | yes | `name`, `description`, `styleUrl`, geometry |
| `Point` | yes | |
| `LineString` | yes | |
| `Polygon` | yes | outer boundary plus any number of inner boundaries |
| `MultiGeometry` | yes | nests, and maps to a GeoJSON `GeometryCollection` |
| `Style` | yes | only styles carrying an `id`, see below |
| `StyleMap` | yes | `normal` and `highlight` pairs |
| `IconStyle` | yes | `scale`, `Icon/href`, `hotSpot` |
| `LabelStyle` | yes | `scale`, `color` |
| `LineStyle` | yes | `color`, `width` |
| `PolyStyle` | yes | `color`, `fill`, `outline` |
| `ExtendedData` | yes | both `Data` pairs and `SchemaData/SimpleData` entries |
| `Folder` | no | folders are flattened, the hierarchy is lost |
| `NetworkLink` | no | |
| `GroundOverlay`, `ScreenOverlay`, `PhotoOverlay` | no | |
| `TimeStamp`, `TimeSpan` | no | |
| `LookAt`, `Camera`, `Region` | no | |
| `gx:Track` and the rest of the `gx:` extensions | no | |

A `Style` declared inline on a Placemark has no `id` and cannot be referenced by a `styleUrl`, so `getStyles()` skips it. Only shared styles appear in the returned map.

Anything in the "no" column is ignored rather than rejected. A document using those elements still parses, you just do not get that data back.

## What you get back

### `getPlacemarks(): array`

A list, one entry per Placemark, in document order. `name` and `description` are always present, `styleUrl` and `extendedData` only when the Placemark declares them.

A `Point` carries a single position:

```php
[
    'name' => 'Lago Blu',
    'description' => 'A lake',
    'type' => 'Point',
    'coordinates' => [
        'longitude' => 7.7,
        'latitude' => 45.8,
        'altitude' => 10.0,
    ],
    'styleUrl' => '#pair',
    'extendedData' => ['area' => '12'],
]
```

A `LineString` carries a list of them:

```php
[
    'name' => 'Route',
    'description' => '',
    'type' => 'LineString',
    'coordinates' => [
        ['longitude' => 7.1, 'latitude' => 45.1, 'altitude' => 0.0],
        ['longitude' => 7.2, 'latitude' => 45.2, 'altitude' => 0.0],
    ],
]
```

A `Polygon` splits its rings:

```php
[
    'name' => 'Area',
    'description' => '',
    'type' => 'Polygon',
    'coordinates' => [
        'outerBoundary' => [
            ['longitude' => 7.0, 'latitude' => 45.0, 'altitude' => 0.0],
            // ...
        ],
        'innerBoundaries' => [
            [
                ['longitude' => 7.1, 'latitude' => 45.1, 'altitude' => 0.0],
                // ...
            ],
        ],
    ],
]
```

`innerBoundaries` is an empty array when the polygon has no holes.

A `MultiGeometry` has no `coordinates` of its own. It carries `geometries` instead, each entry shaped exactly like a standalone geometry of that type, and it can nest:

```php
[
    'name' => 'Mixed',
    'description' => '',
    'type' => 'MultiGeometry',
    'geometries' => [
        ['type' => 'Point', 'coordinates' => [...]],
        ['type' => 'LineString', 'coordinates' => [...]],
    ],
]
```

So switch on `type` before reaching for `coordinates`.

### `getStyles(): array`

Keyed by style `id`. Every sub-style is optional, and inside each one only the elements the document actually declares are reported:

```php
[
    'pin' => [
        'id' => 'pin',
        'iconStyle' => [
            'scale' => 1.2,
            'href' => 'images/icon.png',
            'hotSpot' => ['x' => 32.0, 'y' => 64.0, 'xunits' => 'pixels', 'yunits' => 'insetPixels'],
        ],
        'labelStyle' => ['scale' => 0.8, 'color' => 'ff112233'],
        'lineStyle' => ['color' => 'ff0000ff', 'width' => 4.0],
        'polyStyle' => ['color' => '7f00ff00', 'fill' => false, 'outline' => true],
    ],
]
```

KML defines defaults for all of these (`width` 1, `fill` and `outline` 1, and so on). The parser does not fill them in, so an absent key means the file said nothing about it, not that the value is the default. Apply your own defaults if you need them.

`color` values are returned as the raw KML `aabbggrr` hex string, not converted to `rrggbb`. Note the byte order, KML puts alpha first and blue before red.

### `getStyleMaps(): array`

Keyed by StyleMap `id`:

```php
[
    'pair' => [
        'id' => 'pair',
        'pairs' => [
            'normal' => '#pin',
            'highlight' => '#pin',
        ],
    ],
]
```

### `getDocumentName(): ?string` and `getDocumentDescription(): ?string`

The `name` and `description` of the first `Document` element, or `null` when absent.

### `toGeoJson(): array`

A `FeatureCollection`. Positions come out as `[longitude, latitude, altitude]`, which is the GeoJSON order, and altitude is always present, `0` when the file omits it. `styleUrl` and `extendedData` are carried into `properties`.

```php
[
    'type' => 'FeatureCollection',
    'features' => [
        [
            'type' => 'Feature',
            'properties' => [
                'name' => 'Lago Blu',
                'description' => 'A lake',
                'styleUrl' => '#pair',
                'extendedData' => ['area' => '12'],
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [7.7, 45.8, 10.0],
            ],
        ],
    ],
]
```

A `MultiGeometry` becomes a `GeometryCollection`.

The result is a PHP array, so encode it yourself when you need the wire format:

```php
return response()->json($parser->toGeoJson());
```

## KML namespaces

KML predates the OGC, and exporters still emit the older Google namespaces. All four of these are accepted out of the box:

```
http://www.opengis.net/kml/2.2
http://earth.google.com/kml/2.2
http://earth.google.com/kml/2.1
http://earth.google.com/kml/2.0
```

A document declaring anything else is rejected with `Invalid or missing KML namespace`. Add your own through the `supported_namespaces` config key. XPath always runs against whichever namespace the document actually declares, so a 2.1 file is queried as 2.1.

## KMZ files

A KMZ is a ZIP archive holding a KML file and, usually, the icons it references. `loadFromKmz()` reads the first KML entry it finds and ignores the rest.

To get at the other files, for example to serve the icons:

```php
use PlinCode\KmlParser\KmzExtractor;

$extractor = new KmzExtractor();
$files = $extractor->extractAllFiles('path/to/file.kmz', 'extraction/directory');
```

`extractAllFiles()` returns the list of entry names it wrote. It extracts whatever the archive contains, so point it at a directory you control and treat uploaded archives as untrusted input.

## Error handling

Everything the package throws extends `PlinCode\KmlParser\Exceptions\KmlException`, so one catch is enough to cover it:

```php
use PlinCode\KmlParser\Exceptions\KmlException;

try {
    $placemarks = (new KmlParser())->loadFromFile($path)->getPlacemarks();
} catch (KmlException $e) {
    report($e);
}
```

Catch the subclasses when you need to tell the cases apart:

| Exception | Thrown when |
|---|---|
| `KmlException` | the content is not valid KML: wrong namespace, no `Document`, a Placemark without geometry, coordinates out of range |
| `KmlParserException` | the file is missing, the XML does not parse, or a getter is called before anything was loaded |
| `KmzExtractorException` | the KMZ is missing, is not a readable ZIP, or holds no KML entry |

Coordinate validation runs at load time, so `loadFromString()` rejects a longitude outside -180 to 180 or a latitude outside -90 to 90 before you ever see a placemark.

## Facade

```php
use PlinCode\KmlParser\Facades\KmlParser;

$placemarks = KmlParser::loadFromFile('path/to/file.kml')->getPlacemarks();
```

The facade resolves a container binding that is scoped to the request or queue job, so the loaded document does not carry over between requests under Octane or inside a long running worker. Keep the calls chained, or hold on to the instance, rather than assuming a later `KmlParser::getPlacemarks()` still sees what an earlier call loaded.

## Configuration

```php
return [
    // The primary namespace, also used as a fallback.
    'namespace' => 'http://www.opengis.net/kml/2.2',

    // Namespaces a document is allowed to declare.
    'supported_namespaces' => [
        'http://www.opengis.net/kml/2.2',
        'http://earth.google.com/kml/2.2',
        'http://earth.google.com/kml/2.1',
        'http://earth.google.com/kml/2.0',
    ],

    // Reserved for KMZ extraction. Not used yet.
    'temp_directory' => null,
];
```

## Testing

```bash
composer test
composer analyse
composer format
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

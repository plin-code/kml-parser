# Changelog

All notable changes to `kml-parser` will be documented in this file.

## v3.0.0 - 2026-09-09

A major one day after the last one, which needs explaining: v2.0.0 was about what the parser could read. This one is about what it is willing to run on, and the answer changed for a security reason that should not wait behind a feature release.

### Breaking changes

**Laravel 11 is no longer supported.** `illuminate/contracts` is now `^12.0||^13.0`.

Laravel 11's security window closed in March 2026. The advisories currently open against the framework are fixed in the 12.60/12.61 and 13.10/13.12 lines, and **no 11.x release carries any of them**:

```
Temporary Signed URL Path Confusion   fixed in: 12.61.1, 13.12.0
CRLF injection in default email rule  fixed in: 12.60.0, 13.10.0

```
Declaring `^11.0` told every consumer that an unpatched framework was a supported configuration. `composer audit` is clean on 12 and 13.

Applications still on Laravel 11 stay on `plin-code/kml-parser:^2.0`, which keeps working and has every parser feature this release has. Nothing about the parsing changed here.

### Added

**Archive limits on KMZ files.** A KMZ is a ZIP, and a ZIP can declare a handful of entries that expand into far more than the machine has. Nothing checked. `extractAllFiles()` extracted whatever it was handed and `extractKmlContent()` read an entry straight into a PHP string, so a hostile archive filled the disk or exhausted the worker's memory. Both now validate before reading anything: `max_archive_entries` (default 5000) and `max_uncompressed_size` (default 256 MB). Set either to `0` to turn it off. A real KMZ is a KML plus its icons, nowhere near either number.

**Entry names are checked.** An entry whose name is absolute, starts with a drive letter, or contains a `..` segment is rejected with the offending name in the message. `ZipArchive::extractTo()` does sanitise paths, so this was not exploitable, but the package was relying on that rather than deciding it.

**`temp_directory` finally does something.** It has been in the config since the first release, promising to determine where KMZ files are extracted, with nothing reading it. `extractAllFiles()` now takes an optional destination and falls back to that key, then to the system temp directory, giving each call a directory of its own:

```php
$extractor->extractAllFiles($kmz);              // temp_directory, else sys_get_temp_dir()
$extractor->extractAllFiles($kmz, '/my/path');  // unchanged

```
`defaultDestination()` is public, so a caller can find out where the files went.

**PHP 8.5 and Laravel 13** are tested. The matrix went from 16 jobs to 24: PHP 8.3, 8.4 and 8.5, Laravel 12 and 13, `prefer-lowest` and `prefer-stable`, Linux and Windows. Every combination was resolved and run before being written into the workflow.

### Fixed

**The parser works outside a booted application.** The constructor called `config()` directly, and that helper does not fall back to its second argument when nothing is bound, it throws `BindingResolutionException`. So `new KmlParser` was fatal in a console script or a plain PHPUnit test. Config is now read through a guard that consults the container only when something is bound to it. Behaviour inside an application is unchanged.

**A destination that cannot be created is reported.** `mkdir()`'s return value was ignored, so the failure surfaced later as something unrelated. It now throws `Unable to create the extraction directory: <path>`.

**`extractAllFiles()` throws `KmzExtractorException`** for a missing or unreadable archive, like the rest of the class, instead of a bare `KmlException`. A narrowing rather than a break: `KmzExtractorException extends KmlException`.

### Housekeeping

**The package skeleton residue is gone.** `database/factories/ModelFactory.php` defined nothing at all, its entire class body sat inside a comment block, and the autoloader was mapped to it anyway. `resources/views/` held a `.gitkeep` and no view is ever registered. `TestCase` pointed Eloquent's factory resolution at that empty namespace. PHPStan was told to check model properties, of which there are none. This package parses XML.

**Pest 3 to Pest 4.** Required to reach Laravel 13, since `pest-plugin-laravel` 3.x caps at Laravel 12. The suite needed no changes. Pest 5 was available but requires PHP ^8.4 and would have cost 8.3 support for nothing.

The test suite went from 65 tests to 79.

### Upgrading from 2.x

If you are on Laravel 12 or 13, `composer require plin-code/kml-parser:^3.0` and nothing else changes. The parser, the output shapes and the exceptions are identical to 2.0.

If you are on Laravel 11, stay on `^2.0`, and treat the framework itself as the thing to plan around: the advisories above have no fix in the 11.x line.

Two things to check if you use `KmzExtractor` directly:

- a `catch (KmlException)` around `extractAllFiles()` still works; a check for the exact `KmlException` class no longer matches, it is now `KmzExtractorException`
- an archive over 5000 entries or 256 MB uncompressed is now rejected. Raise `max_archive_entries` or `max_uncompressed_size`, or set either to `0`. The exception message says which limit was hit.

## v2.0.0 - 2026-09-08

The parser accepted a narrower slice of KML than most real files use, and reported several of its own failures as something else. This release fixes both, and changes enough behaviour to need a major.

### Breaking changes

**`getStyles()` no longer returns an entry under the empty key.** It indexed every `//kml:Style` by its `id`, including the anonymous ones declared inline on a Placemark. Those all cast to `''`, collapsed onto the same key and overwrote each other, so the map held whichever inline style came last. Inline styles cannot be referenced by a `styleUrl` anyway, so only shared styles are returned now.

**Validation failures keep their own type.** `loadFromString()` wrapped its whole body in `catch (\Exception)` and rethrew everything as `KmlParserException::failedToParse()`, so "Invalid longitude value: 181" and "this is not XML" were indistinguishable by type. Validation errors now propagate as `KmlException`. A `catch (KmlException)` still covers everything, since `KmlParserException` extends it, but a `catch (KmlParserException)` around a validation failure no longer matches.

**Malformed XML reports a different message.** `Failed to parse KML content: ...` is now `XML parsing error: ...`, thrown through `KmlParserException::invalidXml()`. `failedToParse()` is no longer thrown by anything and is deprecated rather than removed.

**`KmlValidator::$namespace` (string) is now `$namespaces` (array).** Relevant only if you subclass the validator.

**The container binding is `scoped`, not `singleton`.** The parser holds the loaded document as instance state, so under Octane or in a long running queue worker a singleton leaked one request's document into the next. Chained facade calls work exactly as before; two separate facade calls no longer share an instance across a request boundary.

**`altitude` is always a float.** It was a float when the coordinate declared one and the integer `0` when it did not, contradicting the documented array shape. `json_encode()` now writes `0.0` where it wrote `0`.

### Added

**MultiGeometry.** Every document containing one used to be rejected at load with `Empty coordinates in geometry`, because the validator looked for a `coordinates` child that a MultiGeometry does not have. This is what Google My Maps and `ogr2ogr` emit for any feature made of more than one shape, so a large share of real KML could not be parsed at all. Such a placemark now carries `geometries` instead of `coordinates`, nests, and converts to a GeoJSON `GeometryCollection`.

**The legacy Google namespaces.** `http://earth.google.com/kml/2.0`, `2.1` and `2.2` are accepted alongside the OGC `2.2`. Anything else was rejected with `Invalid or missing KML namespace`. XPath now runs against the namespace the document actually declares, so a 2.1 file is queried as 2.1 rather than silently matching nothing. New `supported_namespaces` config key to add your own.

**`LineStyle` and `PolyStyle`.** Only `IconStyle` and `LabelStyle` were parsed, which describe a point marker. Lines and polygons came back with no stroke, width or fill and could not be drawn from the parsed data. `color`, `width`, `fill` and `outline` are now read. Only the elements a document actually declares are reported, so an absent key means the file said nothing rather than that the value is the KML default.

**`SchemaData` and `SimpleData`.** `ExtendedData` only read `<Data>` pairs. `ogr2ogr` and QGIS emit attributes as `<SimpleData>` inside a `<SchemaData>` block, and every one of those was silently dropped. Both forms now land in the same map.

### Fixed

**The `kml-parser.namespace` config key did nothing, and setting it broke everything.** `KmlParser` read it, `KmlValidator` did not and ran first, so validation always demanded 2.2. Pointing the key anywhere else made the parser reject every document.

**libxml error handling is restored.** Both classes called `libxml_use_internal_errors(true)` and never put it back. That is process global: after one KML parse, every other library in the application using libxml stopped emitting warnings for the rest of the request.

**Content is parsed once.** The validator built a `SimpleXMLElement`, discarded it, and the parser built a second one from the same string. Double the CPU and double the peak memory on every load.

**`KmlParserException::invalidXml()` was unreachable**, in two independent ways, and is now what malformed XML actually throws.

**Dead `->n` fallbacks removed** from placemark and document name reading. `n` is not a KML element.

### Documentation

The README now states which KML elements are supported and which are ignored, and gives the exact array shape returned by every getter, taken from real parser output. It also covers the accepted namespaces, the exception hierarchy, and the things that catch people out: MultiGeometry has no `coordinates`, absent style keys are not defaults, and colours stay in KML `aabbggrr` byte order. The `CONTRIBUTING.md` the README has linked to since the first release now exists.

### Infrastructure

Tests and PHPStan run on pull requests, not only on push. Pull requests from forks previously ran no checks at all, while `dependabot-auto-merge.yml` queued every minor and patch bump for auto-merge, so dependency updates could reach `main` untested. Note that this only makes the checks run: marking them as required is branch protection, configured in the repository settings.

The test suite went from 24 tests to 65.

### Upgrading

Most applications need no change. Check for these:

- a `catch (PlinCode\KmlParser\Exceptions\KmlParserException)` that was catching validation errors, widen it to `KmlException`
- a string match on `Failed to parse KML content`
- a read of `getStyles()['']`
- two separate `KmlParser::` facade calls relying on the first one's document being visible to the second
- a fixture comparing GeoJSON output byte for byte, where an omitted altitude now serialises as `0.0`

## Introduce enums, custom exceptions, and KML validation - 2025-04-12

This release introduces key foundational features for the KML parser:

- `GeometryType` and `RequiredElement` enums to represent geometry types and required XML elements.
- `KmlException`, `KmlParserException`, and `KmzExtractorException` for more expressive and structured error handling.
- `KmlValidator` class to perform pre-validation of KML content before XML parsing.
- Integration of the validator into `KmlParser::loadFromString()`.

These changes improve robustness and prepare the parser for more advanced validation and error reporting.

## v1.0.0 - 2025-04-08

### ⚠️ **First Release Disclaimer**

This package is in its early stages. Support for the **KML** format is still evolving as I continue to explore its structure and variations. Feedback and contributions are welcome!

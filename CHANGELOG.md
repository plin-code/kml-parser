# Changelog

All notable changes to `kml-parser` will be documented in this file.

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

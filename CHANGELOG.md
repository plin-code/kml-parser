# Changelog

All notable changes to `kml-parser` will be documented in this file.

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

# Contributing

Thanks for taking the time to contribute.

## Reporting a bug

Open an issue with the KML or KMZ that reproduces it, or the smallest fragment of it that still does. A namespace declaration and one Placemark is usually enough. Say which version of the package, PHP and Laravel you are on.

If the file is confidential, strip the coordinates and the names. The structure is what matters.

## Requesting support for a KML element

The README lists which elements are parsed and which are ignored. If you need one from the second list, open an issue with a sample document and what you expect to get back from `getPlacemarks()` or `toGeoJson()`.

## Pull requests

```bash
composer install
composer test
composer analyse
composer format
```

All three have to pass. CI runs them on every pull request, across PHP 8.3 and 8.4, Laravel 11 and 12, on Linux and Windows.

A few things that make review quick:

- **Add a test that fails without your change.** For a bugfix, write it first and check that it fails on `main`. For a new element, assert the exact array shape you expect, not just that something came back.
- **Run `composer format`.** The project uses Pint and CI will otherwise commit the formatting for you.
- **Keep the change focused.** Unrelated cleanups in the same pull request make it harder to tell what actually changed.
- **Say what breaks.** Changing the shape of a returned array is a breaking change even when no test notices. Call it out in the description so it reaches the changelog.

## Coordinate and namespace changes

Validation is deliberately strict: coordinates outside the valid ranges and unknown namespaces are rejected at load time rather than passed through. If you hit a real document that fails validation, that is a bug worth reporting, but the fix is usually to accept a specific new namespace or format rather than to loosen the check.

## Security

Do not open a public issue for a security problem. See the [security policy](../../security/policy).

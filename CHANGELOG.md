# CHANGELOG

## 2.3.0

* Updated to require PHP 8.2 and support PHP 8.5.
* Added the `Reader::getWhere()` method to allow row filtering.

## 2.2.0

* Added the ability for `Reader` to skip columns by specifying an empty column
  name.

## 2.1.0

* Added the ability to specify a file handle for the `file` parameter.

## 2.0.0

* Removed support for PHP 8.0.
* Migrated `Reader` and `Writer` configuration parameters from
  `\Ork\Core\ConfigurableTrait` to named constructor parameters.
* The `$column` parameter of the `Reader::toArray()` method has been removed.
  Specification of a custom array key is now accomplished via the `keyByColumn`
  parameter, which preserves the iterative nature of the resulting output.
* Added `Reader` parameter `detectDuplicateKeys`.
* Added `Writer` paramter `appendToExistingFile`.
* Renamed `Writer` parameter from `strict` to `allowUnknownColumns`.
* Renamed `Writer` method from `writeFromIterator()` to `writeFrom()`.

## 1.6.0

* Updated to require PHP 8.0.

## 1.5.1

* Updated PHPStan to 1.1.

## 1.5.0

* Removed support for PHP 7.3.
* Migrated from Travis to Github Actions.

## 1.4.1

* Updated dependencies.
* Changed test namespace.
* This is the last version that supports PHP 7.3.

## 1.4.0

* Added support for PHP 8.0.
* Added `strict` parameter to Writer.
* Added `getLineCount` method to Writer.
* Added `writeFromIterator` method to Writer.
* Disassociate `header` parameter from whether or not the input array is
  indexed or associative

## 1.3.0

* Removed support for PHP 7.2.

## 1.2.1

* Prevent E_WARNING on reader column mismatch.
* This is the last version that supports PHP 7.2.

## 1.2.0

* Added callbacks feature to Writer.

## 1.1.1

* Make sure we have unique names when overriding columns.

## 1.1.0

* Added the ability to provide column names when reading headerless files,
  and override column names when reading headered files.

## 1.0.3

* Updated dependecies.
* Added PHPStan validation.
* Moved wiki docs into this repo.

## 1.0.2

* Added Reader::getColumns() method.

## 1.0.1

* Avoid the use of file_exists() so that we can use fopen wrappers.

## 1.0.0

* Initial version.

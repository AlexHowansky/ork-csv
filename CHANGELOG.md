# CHANGELOG

## 1.2.1

* Prevent E_WARNING on reader column mismatch.

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

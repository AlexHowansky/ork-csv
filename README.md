# Ork CSV

Ork CSV is a library for reading and writing CSV files.

[![Latest Stable Version](https://img.shields.io/packagist/v/ork/csv.svg?style=flat)](https://packagist.org/packages/ork/csv)
[![PHPStan Enabled](https://img.shields.io/badge/PHPStan-max-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![PHP](https://img.shields.io/packagist/php-v/ork/csv.svg?style=flat)](http://php.net)
[![License](https://img.shields.io/github/license/AlexHowansky/ork-csv.svg?style=flat)](https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/AlexHowansky/ork-csv/tests?style=flat&label=workflow)](https://github.com/AlexHowansky/ork-csv/actions?query=workflow%3Atests)
[![Travis Build Status](https://img.shields.io/travis/AlexHowansky/ork-csv/master.svg?style=flat&label=Travis)](https://secure.travis-ci.org/AlexHowansky/ork-csv)

## Requirements

* PHP 7.3

## Installation

### Via command line

```bash
composer require ork/csv
```

### Via composer.json

```json
"require": {
    "ork/csv": "*"
},
```

## Documentation

Consider a CSV containing:

    Id,Name
    1,foo
    2,bar

A reader object will provide a generator that yields one row per iteration.
Each row will consist of an associative array indexed by the values provided
in the file's header row. Basic usage is simple:

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file.csv',
]);
foreach ($csv as $row) {
    echo "id is: " . $row['Id'] . "\n";
    echo "name is: " . $row['Name'] . "\n";
}
```

See the [docs](docs/Index.md) directory for full details.

## Development

### Coding Style Validation

Coding style validation is performed by [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer).
A compser alias is provided to run the validation.

```bash
composer phpcs
```

### Static Analysis

Static analysis is performed by [PHPStan](https://github.com/phpstan/phpstan).
A composer alias is provided to run the analysis.

```bash
composer phpstan
```

### Unit Testing

Unit testing is performed by [PHPUnit](https://github.com/sebastianbergmann/phpunit).
A composer alias is provided to run the tests.

```bash
composer test
```

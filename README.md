# Ork CSV

Ork CSV is a library for reading and writing CSV files.

[![Latest Stable Version](https://img.shields.io/packagist/v/ork/csv.svg?style=flat)][1]
[![PHP](https://img.shields.io/packagist/php-v/ork/csv.svg?style=flat)][3]
[![PHPStan Enabled](https://img.shields.io/badge/PHPStan-max-brightgreen.svg?style=flat)][2]
[![License](https://img.shields.io/github/license/AlexHowansky/ork-csv.svg?style=flat)][4]
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/AlexHowansky/ork-csv/tests?style=flat&label=workflow)][5]

## Requirements

* PHP 7.3
* PHP 7.4
* PHP 8.0
* PHP 8.1

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

### Reader

Consider a CSV file containing:

```csv
Id,Name,Size
1,foo,large
2,bar,small
```

A reader object will provide a generator that yields one row per iteration.
Each row will consist of an associative array indexed by the values provided
in the file's header row.

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file.csv',
]);
foreach ($csv as $row) {
    echo "id is: " . $row['Id'] . "\n";
    echo "name is: " . $row['Name'] . "\n";
    echo "size is: " . $row['Size'] . "\n";
}
```

### Writer

A writer object will track columns and automatically generate an appropriate
header row.

```php
$csv = new \Ork\Csv\Writer([
    'file' => '/path/to/file.csv',
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'Id' => 2,
    'Name' => 'bar',
    'Size' => 'small',
]);
```

The output generated will be:

```csv
Id,Name,Size
1,foo,large
2,bar,small
```

See the [docs](docs/Index.md) directory for full details.

## Development

### Coding Style Validation

Coding style validation is performed by [PHP CodeSniffer][7]. A composer alias
is provided to run the validation.

```bash
composer phpcs
```

### Static Analysis

Static analysis is performed by [PHPStan][8]. A composer alias is provided to
run the analysis.

```bash
composer phpstan
```

### Unit Testing

Unit testing is performed by [PHPUnit][9]. A composer alias is provided to run
the tests.

```bash
composer test
```

[1]: https://packagist.org/packages/ork/csv
[2]: https://github.com/phpstan/phpstan
[3]: http://php.net
[4]: https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE
[5]: https://github.com/AlexHowansky/ork-csv/actions?query=workflow%3Atests
[6]: https://secure.travis-ci.org/AlexHowansky/ork-csv
[7]: https://github.com/squizlabs/PHP_CodeSniffer
[8]: https://github.com/phpstan/phpstan
[9]: https://github.com/sebastianbergmann/phpunit

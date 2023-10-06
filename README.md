# Ork CSV

Ork CSV is a library for reading and writing CSV files.

[![Latest Version](https://img.shields.io/packagist/v/ork/csv.svg)][1]
[![PHP](https://img.shields.io/packagist/php-v/ork/csv.svg)][2]
[![License](https://img.shields.io/github/license/AlexHowansky/ork-csv.svg)][3]
[![PHPStan](https://img.shields.io/badge/PHPStan-8-brightgreen.svg)][4]
[![Workflow Status](https://img.shields.io/github/workflow/status/AlexHowansky/ork-csv/tests?label=tests)][5]
[![Code Coverage](https://img.shields.io/codecov/c/github/AlexHowansky/ork-csv)][6]

## Installation

```bash
composer require ork/csv
```

### Reader

Ork CSV provides a reader to parse delimited files into arrays. The reader is
implemented as an iterator that yields the contents of one CSV line per
iteration. If the file has a header line with column names, each yielded array
will be associative, keyed by the names in the header. If the file does not have
a header line with columns names, each yielded array will be indexed.

For example, a file with a header line:

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader('/path/to/file.csv');
foreach ($csv as $row) {
    print_r($row);
}
```

```text
Array
(
    [ID] => 1
    [Name] => foo
    [Size] => large
)
Array
(
    [ID] => 2
    [Name] => bar
    [Size] => small
)
```

A file without a header line:

```csv
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(file: '/path/to/file.csv', hasHeader: false);
foreach ($csv as $row) {
    print_r($row);
}
```

```text
Array
(
    [0] => 1
    [1] => foo
    [2] => large
)
Array
(
    [0] => 2
    [1] => bar
    [2] => small
)
```

### Writer

Ork CSV provides a writer that will track columns and automatically generate an
appropriate header line.

```php
$csv = new \Ork\Csv\Writer('/path/to/file.csv');
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

```csv
Id,Name,Size
1,foo,large
2,bar,small
```

See the [docs](docs/Index.md) directory for full details.

[1]: https://packagist.org/packages/ork/csv
[2]: https://php.net
[3]: https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE
[4]: https://github.com/phpstan/phpstan
[5]: https://github.com/AlexHowansky/ork-csv/actions/workflows/tests.yml
[6]: https://app.codecov.io/gh/AlexHowansky/ork-csv

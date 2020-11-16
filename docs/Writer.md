# \Ork\Csv\Writer

`\Ork\Csv\Writer` is a CSV writer. It automatically handles header rows and
arbitrary field filtering, and is intended to be called inside a loop with an
associative array as input.

## Usage

Pass an array to the `write()` method to output a single CSV row:

```php
$csv = new \Ork\Csv\Writer();
foreach ($rows as $row) {
    $csv->write($row);
}
```

Or pass an iterable to `writeFromIterator()` to output all records.

```php
$csv = new \Ork\Csv\Writer();
$csv->writeFromIterator($rows);
```

### With Implicit Header Row

This is the default behavior. Provide associative arrays to the `write()`
method and the keys from the first array processed will be used for the column
names in the header row. The order of the items in subsequent rows is
irrelevant.

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
    'Size' => 'small',
    'Name' => 'bar',
    'Id' => 2,
]);
```

Output:

```csv
Id,Name,Size
1,foo,large
2,bar,small
```

### With Explicit Header Row

You can explicitly specify the column list via the `columns` parameter. This
is generally only necessary when your data arrays might not have all columns.
In this case, you also probably want to set the `strict` parameter to false.

```php
$csv = new \Ork\Csv\Writer([
    'file' => '/path/to/file.csv',
    'columns' => ['Id', 'Name', 'Size'],
    'strict' => false,
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
]);
$csv->write([
    'Id' => 2,
    'Size' => 'small',
]);
```

Generates:

```csv
Id,Name,Size
1,foo,
2,,small
```

### Without Header Row

To create a file with no header row, pass `false` to the `header` parameter.
This is generally used when your input arrays are indexed rather than
associative.

```php
$csv = new \Ork\Csv\Writer([
    'file' => '/path/to/file.csv',
    'header' => false,
]);
$csv->write([1, 2, 3]);
$csv->write([4, 5, 6]);
```

Output:

```csv
1,2,3
4,5,6
```

## Configuration

`\Ork\Csv\Writer` uses [`\Ork\Core\ConfigurableTrait`][1]. Its configuration
parameters are as follows:

### `file` *string*

The file to write to. Defaults to: `php://stdout`

### `header` *bool*

Set to `true` to generate a header row with column names. Defaults to: `true`

### `columns` *array\<string\>*

The list of columns expected to appear in each row. This is used to generate
the header row and validate columns in `strict` mode. If not specified, the
list will be built automatically from the columns (i.e., the array keys) in the
first row encountered.

### `strict` *bool*

If `true`, then an exception will be thrown when encountering columns not
specified in the column list. If `false`, then unknown columns will be quietly
ignored.

```php
$csv = new \Ork\Csv\Writer([
    'strict' => true,
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
    'NewField' => 'unknown', // This field generates an error.
]);
```

```php
$csv = new \Ork\Csv\Writer([
    'strict' => false,
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
    'NewField' => 'unknown', // This field is quietly skipped.
]);
```

Output:

```csv
Id,Name,Size
1,foo,large
2,bar,small
```

### `delimiter` *string*

The field delimiter character. Defaults to comma: `,`

```php
$tsv = new \Ork\Csv\Writer([
    'delimiter' => "\t",
]);
```

```php
$psv = new \Ork\Csv\Writer([
    'delimiter' => '|',
]);
```

### `escape` *string*

The escape character. Defaults to blackslash: `\`

### `quote` *string*

The quote character. Defaults to double quote: `"`

### `callbacks` *array\<string|int, callable\>*

Defines optional callback functions to be run on values before they're written.
The functions should take one argument (the value about to be written) and
return the processed value. If more than one callable is provided, they will be
called in the order specified. If you use the `[$object, $method]` style of
callable specification, you must place it in an array even if you only have one
callable.

Specify an associative array where the key is the name of the column to apply
the callback to, and the value is a callable or an array of callables.

```php
$csv = new \Ork\Csv\Writer([
    'callbacks' => [
        'Id' => function ($value) { return ... },
        'Name' => ['trim', 'strtoupper'],
        'Size' => [[$object, $method]],
        'Count' => 'number_format',
    ],
]);
```

If the column name begins with a slash, it will be treated as a regex pattern
and applied to all column names that match. To apply a callback to all columns,
specify a pattern that matches all your field names, like `/./`.

```php
$csv = new \Ork\Csv\Writer([
    'callbacks' => [
        '/Date$/' => function ($value) {
            return (new DateTime($value))
                ->setTimeZone(new DateTimeZone('UTC'))
                ->format('c');
        },
    ],
]);
$csv->write([
    'startDate' => '-3 weeks',
    'endDate' => 'yesterday',
]);
```

If your input data comes from indexed arrays, then your columns are
effectively named `0`, `1`, `2`, etc.

```php
$csv = new \Ork\Csv\Writer([
    'callbacks' => [
        2 => 'strtoupper',
    ],
    'header' => false,
]);
$csv->write(['foo', 'bar', 'baz']);
```

Output:

```csv
foo,bar,BAZ
```

[1]: https://github.com/AlexHowansky/ork-core/wiki/ConfigurableTrait

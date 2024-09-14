# \Ork\Csv\Writer

* [Usage](#usage)
  * [With Implicit Header Line](#with-implicit-header-line)
  * [With Explicit Header Line](#with-explicit-header-line)
  * [Without Header Line](#without-header-line)
* [Configuration](#configuration)
  * [file](#file)
  * [hasHeader](#hasheader)
  * [columnNames](#columnnames)
  * [callbacks](#callbacks)
  * [appendToExistingFile](#appendtoexistingfile)
  * [allowUnknownColumns](#allowunknowncolumns)
  * [delimiterCharacter](#delimitercharacter)
  * [quoteCharacter](#quotecharacter)
  * [escapeCharacter](#escapecharacter)
* [Writing From an Iterator](#writing-from-an-iterator)

## Usage

`\Ork\Csv\Writer` is a CSV writer. It automatically handles header lines and
arbitrary field filtering, and is intended to be called inside a loop with an
associative array as input.

### With Implicit Header Line

To output a CSV line, pass an associative array to the `write()` method. The
array keys from the first record encountered will be used to generate a header
line with column names. Subsequent records will include only those columns.

```php
$csv = new \Ork\Csv\Writer('/path/to/file.csv');
$csv->write([
    'ID' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'ID' => 2,
    'Name' => 'bar',
    'Size' => 'small',
    'Extra' => 'this column is not included',
]);
```

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

### With Explicit Header Line

If explicit control of the header line is desired, a list of column names may be
provided via the `columnNames` parameter. This is especially useful if not all
data records contain all columns. In this case, the correct position of each
column will be maintained.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    columnNames: ['Id', 'Name', 'Size'],
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

```csv
Id,Name,Size
1,foo,
2,,small
```

### Without Header Line

To create a file with no header line, set the `hasHeader` parameter to `false`.
This is generally useful when the data records are indexed rather than
associative.

```php
$csv = new \Ork\Csv\Writer(file: '/path/to/file.csv', hasHeader: false]);
$csv->write([1, 2, 3]);
$csv->write([4, 5, 6]);
```

```csv
1,2,3
4,5,6
```

## Configuration

The `\Ork\Csv\Writer` constructor takes the following optional paramters:

### `file`

Default: `php://stdout`

The file to write to. This may be a string containing any value that PHP treats
as a file reference (including `php://` specials and writable stream wrappers)
or an open file handle.

### `hasHeader`

Default: `true`

Set to `true` to generate a header line with column names.

### `columnNames`

Default: `[]`

The list of columns expected to appear in each record. These values are used to
generate the header line. If not specified, the column names will be taken from
the array keys in the first record encountered.

### `callbacks`

Default: `[]`

Defines callback functions to pre-process values before they are written. The
functions should take one argument and return one value. Multiple functions may
be chained, and will be performed in the order specified. Specify callbacks in
an associative array where the key of the array is the name of the column to
apply the callbacks to (or the index if no column names are configured) and the
value of the array is one or more functions to apply to that column.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    callbacks: [
        'Name' => 'strrev',
        'Size' => ['strtoupper', 'lcfirst'],
    ],
);
$csv->write([
    'ID' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'ID' => 2,
    'Name' => 'bar',
    'Size' => 'small',
]);
```

```csv
ID,Name,Size
1,oof,lARGE
2,rab,sMALL
```

Callbacks are specified as a PHP `callable`, so may be an inline anonymous or
arrow function, a string containing the name of a function, an array of object
and method name, or a reference to a method using first class callable syntax.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    callbacks: [
        'ID' => fn($id) => $id * 2,
        'Name' => [
            'strrev',
            'SomeClass::SomeStaticMethod',
        ],
        'Size' => [
            [$object, 'method'],
            $object->method(...),
        ],
    ],
);
```

Note that if using the `[$object, $method]` format and providing only one
callback, it will need to be specified as an embedded array so that it is not
interpreted as two separate callbacks `$object` and then `$method`.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    callbacks: [
        'field' => [[$object, $method]],
    ]
);
```

If the callback column name starts with a slash `/`, it will be interpreted as a
regex pattern and applied to all column names that match. To apply a callback to
all columns, specify a pattern that matches all your field names, like `/./`.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    callbacks: [
        '/date$/i' => fn($date) => (new DateTime($date))->format('c'),
        '/./' => 'trim',
    ],
);
$csv->write([
    'id' => 1,
    'startDate' => '-3 weeks',
    'endDate' => 'yesterday',
]);
```

If the input data records are indexed arrays and no explicit column names have
been specified, then the columns are effectively named by their index `0`, `1`,
`2`, etc.

```php
$csv = new \Ork\Csv\Writer(
    file: '/path/to/file.csv',
    hasHeader: false,
    callbacks: [
        1 => fn($v) => $v * 2,
    ]
]);
$csv->write([1, 2, 3]);
$csv->write([4, 5, 6]);
```

```csv
1,4,3
4,10,6
```

### `appendToExistingFile`

Default: `false`

This setting has an effect only when the file specified in `file` already
exists. If `true`, records will be appended. If `false`, the file be
overwritten.

### `allowUnknownColumns`

Default: `true`

If `false`, an exception will be thrown when encountering a column that does not
exist in the established column list.

```php
$csv = new \Ork\Csv\Writer(file: '/path/to/file.csv', allowUnknownColumns: false);
// The list of allowed columns is established by the keys in the first record.
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'Id' => 2,
    'Name' => 'bar',
    'Size' => 'small ',
    'NewField' => 'unknown', // This unknown field generates an exception.
]);
```

If `true`, unknown columns will be quietly ignored.

```php
$csv = new \Ork\Csv\Writer(file: '/path/to/file.csv', allowUnknownColumns: true);
$csv->write([
    'Id' => 1,
    'Name' => 'foo',
    'Size' => 'large',
]);
$csv->write([
    'Id' => 2,
    'Name' => 'bar',
    'Size' => 'small ',
    'NewField' => 'unknown', // This unknown field will be ignored.
]);
```

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

### `delimiterCharacter`

Default: `,`

The field delimiter character used in the CSV file.

```php
$tabSeparatedWriter = new \Ork\Csv\Writer(delimiterCharacter: "\t");
```

### `quoteCharacter`

Default: `"`

The quote character used in the CSV file. This is used to enclose a value that
might include the field delimiter character.

### `escapeCharacter`

Default: `\`

The escape character used in the CSV file. This is used to escape a quote
character that might be included in a value.

## Writing From an Iterator

Use the `writeFrom()` method to write all the records from an iterable.

```php
$csv = new \Ork\Csv\Writer('/path/to/file.csv');
$csv->writeFrom([
    [
        'Id' => 1,
        'Name' => 'foo',
        'Size' => 'large',
    ],
    [
        'Id' => 2,
        'Name' => 'bar',
        'Size' => 'small',
    ]
]);
```

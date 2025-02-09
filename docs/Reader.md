# \Ork\Csv\Reader

* [Usage](#usage)
  * [With Header Line](#with-header-line)
  * [Without Header Line](#without-header-line)
* [Configuration](#configuration)
  * [file](#file)
  * [hasHeader](#hasheader)
  * [columnNames](#columnnames)
  * [callbacks](#callbacks)
  * [keyByColumn](#keybycolumn)
  * [detectDuplicates](#detectduplicates)
  * [delimiterCharacter](#delimitercharacter)
  * [quoteCharacter](#quotecharacter)
  * [escapeCharacter](#escapecharacter)
* [Column Names](#column-names)
* [Using Only a Single Column](#using-only-a-single-column)
* [Line Number](#line-number)
* [Array Casting](#array-casting)

## Usage

`\Ork\Csv\Reader` is implemented as an iterator that will yield the contents of
one CSV line per iteration. Very large files can be processed without the need
to load the entire file into memory.

### With Header Line

If the file has a header line, its values will be interpreted as column names,
and each iteration will yield an associative array with those values as keys.

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

### Without Header Line

If the file does not have a header line, each iteration will yield a
zero-indexed array.

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

## Configuration

The `\Ork\Csv\Reader` constructor takes the following optional paramters:

### `file`

Default: `php://stdin`

The file to read from. This may be a string containing any value that PHP treats
as a file reference (including `php://` specials, stream wrappers, and URLs) or
an open file handle.

### `hasHeader`

Default: `true`

Set to `true` if the file has a header line that contains column names.

### `columnNames`

Default: `[]`

The names to assign to columns. This can be used to provide column names for a
file that does not have a header line.

```csv
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    hasHeader: false,
    columnNames: ['ID', 'Name', 'Size']
);
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

If not all columns are desired in the output, they may be skipped by providing
empty column names for their position.

```csv
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    hasHeader: false,
    columnNames: ['One', null, 'Three']
);
foreach ($csv as $row) {
    print_r($row);
}
```

```text
Array
(
    [One] => 1
    [Three] => large
)
Array
(
    [One] => 2
    [Three] => small
)
```

Column names may also be overridden for files that have a header line. If
`hasHeader` is `true` and `columnNames` is empty (the default), the values from
the header line are used as column names. If `hasHeader` is `true` and
`columnNames` is not empty, those values are used instead.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    hasHeader: true,
    columnNames: ['One', 'Two', 'Three']
);
foreach ($csv as $row) {
    print_r($row);
}
```

```text
Array
(
    [One] => 1
    [Two] => foo
    [Three] => large
)
Array
(
    [One] => 2
    [Two] => bar
    [Three] => small
)
```

### `callbacks`

Default: `[]`

Defines callback functions to post-process values after they have been read. The
functions should take one argument and return one value. Multiple functions may
be chained, and will be performed in the order specified. Specify callbacks in
an associative array where the key of the array is the name of the column to
apply the callbacks to (or the index if no column names are configured) and the
value of the array is one or more functions to apply to that column.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    callbacks: [
        'Name' => 'strrev',
        'Size' => ['strtoupper', 'lcfirst'],
    ]
]);
foreach ($csv as $row) {
    print_r($row);
}
```

```text
Array
(
    [0] => Array
        (
            [ID] => 1
            [Name] => oof
            [Size] => lARGE
        )

    [1] => Array
        (
            [ID] => 2
            [Name] => rab
            [Size] => sMALL
        )

    [2] => Array
        (
            [ID] => 3
            [Name] => rab
            [Size] => hUGE
        )

)
```

Callbacks are specified as a PHP `callable`, so may be an inline anonymous or
arrow function, a string containing the name of a function, an array of object
and method name, or a reference to a method using first class callable syntax.

```php
$csv = new \Ork\Csv\Reader(
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
    ]
);
```

Note that if using the `[$object, $method]` format and providing only one
callback, it will need to be specified as an embedded array so that it is not
interpreted as two separate callbacks `$object` and then `$method`.

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    callbacks: [
        'field' => [[$object, $method]],
    ]
);
```

If the callback column name starts with a slash `/`, it will be interpreted as a
regex pattern and applied to all column names that match. To apply a callback to
all columns, specify a pattern that matches all your field names, like `/./`.

```csv
ID,Name,Date
 1 , foo , 2020-01-01T01:02:03+00:00
 2 , bar , 2021-02-02T02:03:04+00:00
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    callbacks => [
        '/./' => 'trim',
        '/date$/i' => fn($v) => new DateTime($v),
    ],
);
```

```text
Array
(
    [0] => Array
        (
            [ID] => 1
            [Name] => foo
            [Date] => DateTime Object
                (
                    [date] => 2020-01-01 01:02:03.000000
                    [timezone_type] => 1
                    [timezone] => +00:00
                )

        )

    [1] => Array
        (
            [ID] => 2
            [Name] => bar
            [Date] => DateTime Object
                (
                    [date] => 2021-02-02 02:03:04.000000
                    [timezone_type] => 1
                    [timezone] => +00:00
                )

        )

)
```

### `keyByColumn`

Default: `null`

If specified, the key of each yielded array will be taken from the indicated
column. If the file has column names, either via a header line or `columnNames`,
specify the name of the desired column. If the file has no header line or
explicitly-provided column names, specify the zero-indexed column number. This
is most useful when casting the entire file to an array.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(file: '/path/to/file.csv', keyByColumn: 'Name');
print_r($csv->toArray());
```

```text
Array
(
    [foo] => Array
        (
            [ID] => 1
            [Name] => foo
            [Size] => large
        )

    [bar] => Array
        (
            [ID] => 2
            [Name] => bar
            [Size] => small
        )

)
```

Alternatively, keep the iterative behavior intact by processing one line at
time.

```php
$csv = new \Ork\Csv\Reader(file: '/path/to/file.csv', keyByColumn: 'Name');
foreach ($csv as $key => $row) {
    echo "Key is: $key\n";
    print_r($row);
}
```

```text
Key is: foo
Array
(
    [ID] => 1
    [Name] => foo
    [Size] => large
)
Key is: bar
Array
(
    [ID] => 2
    [Name] => bar
    [Size] => small
)
```

### `detectDuplicates`

Default: `true`

If `keyByColumn` is specified and the file contains multiple lines with the same
value for that column, we may encounter some undesired behavior. To prevent
this, set `detectDuplicates` to `true`, and an exception will the thrown when
duplicate keys are encountered. Set `detectDuplicates` to `false` with caution.
If `false` and duplicate keys are present in the file, behavior will vary
depending how the file is consumed. If casting the entire file to an array with
`toArray()`, only the last of the matching lines will appear in the result
set.

```csv
ID,Name,Size
1,foo,large
2,bar,small
3,bar,huge
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    keyByColumn: 'Name',
    detectDuplicateKeys: false
);
print_r($csv->toArray());
```

Note that the row with ID 2 never appears in our result:

```text
Array
(
    [foo] => Array
        (
            [ID] => 1
            [Name] => foo
            [Size] => large
        )

    [bar] => Array
        (
            [ID] => 3
            [Name] => bar
            [Size] => huge
        )

)
```

However, if iterating, the same key will be received multiple times, which is
counter-intuitive for what appears to be an associative array key.

```csv
ID,Name,Size
1,foo,large
2,bar,small
3,bar,huge
```

```php
$csv = new \Ork\Csv\Reader(
    file: '/path/to/file.csv',
    keyByColumn: 'Name',
    detectDuplicateKeys: false
);
foreach ($csv as $key => $row) {
    echo "Key is: $key\n";
}
```

Note we get `bar` twice:

```text
Key is: foo
Key is: bar
Key is: bar
```

### `delimiterCharacter`

Default: `,`

The field delimiter character used in the CSV file.

```php
$tabSeparatedReader = new \Ork\Csv\Reader(delimiterCharacter: "\t");
```

### `quoteCharacter`

Default: `"`

The quote character used in the CSV file. This is used to enclose a value that
might include the field delimiter character.

### `escapeCharacter`

Default: `\`

The escape character used in the CSV file. This is used to escape a quote
character that might be included in a value.

## Column Names

When processing a file with a header line, the detected column names may be
retreived with the `getColumnNames()` method.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader('/path/to/file.csv');
print_r($csv->getColumnNames());
```

```text
Array
(
    [0] => ID
    [1] => Name
    [2] => Size
)
```

## Using Only a Single Column

Iterate over the values from just one column via the `getColumn()` method. If
the file has column names, either via a header line or `columnNames`, specify
the name of the desired column.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader('/path/to/file.csv');
foreach ($csv->getColumn('name') as $value) {
    echo "$name\n";
}
```

```text
foo
bar
```

If the file has no header line or explicitly-provided column names, specify the
zero-indexed column number.

```csv
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader(file: '/path/to/file.csv', hasHeader: false);
foreach ($csv->getColumn(1) as $value) {
    echo "$name\n";
}
```

```text
foo
bar
```

## Line Number

The most recently processed line number is available via the `getLineNumber()`
method. This value is one-indexed and includes the header line, if any. Thus,
the first iteration of a file with a header line will yield a line number of 2.

## Array Casting

The entire file may be cast to an array via the `toArray()` method. Use this
with caution, as it loads the entire file into memory at once.

```csv
ID,Name,Size
1,foo,large
2,bar,small
```

```php
$csv = new \Ork\Csv\Reader('/path/to/file.csv');
print_r($csv->toArray());
```

```text
Array
(
    [0] => Array
        (
            [ID] => 1
            [Name] => foo
            [Size] => large
        )

    [1] => Array
        (
            [ID] => 2
            [Name] => bar
            [Size] => small
        )

)
```

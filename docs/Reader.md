# \Ork\Csv\Reader

`\Ork\Csv\Reader` is a CSV reader.

## Usage

`\Ork\Csv\Reader` is implemented as an iterator.

### With Header Row

If the file has a header row, each iteration of the object will yield an
associative array where the keys are taken from the header row.

```csv
id,name,description
1,foo,Something to foo with.
2,bar,Something to bar with.
```

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
]);
foreach ($csv as $row) {
    print_r($row);
}
```

Output:

```text
Array
(
    [id] => 1
    [name] => foo
    [description] => Something to foo with.
)
Array
(
    [id] => 2
    [name] => bar
    [description] => Something to bar with.
)
```

### Without Header Row

If the file does not have a header row, each iteration of the object will
yield a zero-indexed array containing the values from the row.

```csv
one,two,three
four,five,six
```

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
    'header' => false,
]);
foreach ($csv as $row) {
    print_r($row);
}
```

Output:

```text
Array
(
    [0] => one
    [1] => two
    [2] => three
)
Array
(
    [0] => four
    [1] => five
    [2] => six
)
```

## Configuration

`\Ork\Csv\Writer` uses [`\Ork\Core\ConfigurableTrait`][1]. Its configuration
parameters are as follows:

### `file` *string*

The file to write to. Defaults to: `php://stdin`

### `header` *bool*

Set to `true` if the file has a header row. Defaults to: `true`

### `columns` *array*

The names to assign to columns. If `header` is true and this is empty (the
default), the values from the header row are used as column names. If
`header` is true and this is not empty, these values are used instead.

### `callbacks` *array\<string|int, callable\>*

Defines optional callback functions to be run on values after they're read.

### `delimiter` *string*

The field delimiter character. Defaults to comma: `,`

```php
$tsv = new \Ork\Csv\Reader([
    'delimiter' => "\t",
]);
```

```php
$psv = new \Ork\Csv\Reader([
    'delimiter' => '|',
]);
```

### `escape` *string*

The escape character. Defaults to blackslash: `\`

### `quote` *string*

The quote character. Defaults to double quote: `"`

## Fetching a Single Column

You may iterate over the values for just one column. If the file does not have
a header row, the column parameter should be the zero-indexed column number.
(This is zero-indexed to ensure that values referenced in this manner match
those referenced by the array index in the full-row technique.) If the file
does have a header row, the column parameter should be the name of the column.

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
    'header' => false,
]);
foreach ($csv->getColumn(0) as $value) {
    // $value contains the value from the zeroth column.
}
```

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
    'header' => true,
]);
foreach ($csv->getColumn('name') as $value) {
    // $value contains the value from the "name" column.
}
```

## Current Line Number

The current line number is available via the `getLineNumber()` method. Note
this is one-indexed and includes the header line, if any. Thus, the first
iteration of a file with a header row will yield a line number of 2.

## Array Casting

The entire file may be cast to an array via the `toArray()` method. A file
without a header row will be cast to an indexed array of indexed arrays. A file
with a header row will be cast to an indexed array of associative arrays. Note
that this effectively loads the entire file into memory at once.

## Callbacks

Callback filters may be defined to post-process fields. After reading each row,
the list of callbacks will be processed in the order defined.

```csv
id,name,description
1,foo,Something to foo with.
2,bar,Something to bar with.
```

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        'name' => 'strtoupper',
    ],
]);
foreach ($csv as $row) {
    echo $row['name'] . "\n";
}
```

Output:

```text
FOO
BAR
```

Callbacks are specified in the standard PHP fashion -- as a closure, the
name of a function, or an array of object and method name. Multiple callbacks
may be specified as an array.

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        'field1' => 'strtolower',
        'field2' => 'Obj::method',
        'field3' => ['strtolower', 'Obj::method', [new Obj(), 'method']],
        'field4' => function ($value) { ... },
    ],
]);
```

Note that if you use the `[$obj, $method]` format and you only have one filter,
you'll need to specify it as an embedded array so that it's not interpreted as
`$obj` then `$method`.

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        'field1' => [[new Obj(), 'method']],
    ],
]);
```

If the callback column name starts with a slash `/` then it will be
interpreted as a regex and applied to all columns that match.

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        '/^foo_/i' => [[new Obj(), 'method']],
        '/./' => [[new Obj(), 'method']],
    ],
]);
```

[1]: https://github.com/AlexHowansky/ork-core/wiki/ConfigurableTrait

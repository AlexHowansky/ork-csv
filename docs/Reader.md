# \Ork\Csv\Reader

`\Ork\Csv\Reader` is a CSV reader.

## Configuration

`\Ork\Csv\Reader` uses [`\Ork\Core\ConfigurableTrait`](https://github.com/AlexHowansky/ork-core/wiki/ConfigurableTrait).
Its configuration attributes are as follows:

|Name|Type|Description|
|----|----|-----------|
|callbacks|array|Defines callback functions to be run on the values after they're read. If using column names, the array index should be the name of the column to apply callbacks to. Alternatively, if the index string begins with a slash, it will be treated as a regex and applied to all matching columns. If not using column names, the array index should be the numerical index (0-based) of the column to apply the callback(s) to. The value for each entry can be a single callable or an array of callables. Each callable should expect one parameter and return one value.|
|columns|array|The names to assign to columns. If `header` is true and this is empty (the default), the values from the header row are used as column names. If `header` is true and this is not empty, these values are used instead.|
|delimiter|string|The field delimiter character. Defaults to comma: `,`|
|escape|string|The escape character. Defaults to blackslash: `\`|
|file|string|The file to process. Defaults to: `php://stdin`|
|header|bool|Whether the first row contains column names. Defaults to: `true`|
|quote|string|The quote character. Defaults to double quote: `"`|

## Iterator Usage

`\Ork\Csv\Reader` is implemented as an iterator, so usage is quite simple.

### Without Header Row

If the file has no headers, each iteration of the object will yield a
zero-indexed array containing the values from the row. For example, consider
a CSV file containing:

```csv
one,two,three
four,five,six
```

The following code:

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
    'header' => false,
]);
foreach ($csv as $row) {
    print_r($row);
}
```

Will output:

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

### With Header Row

If the file has headers, each iteration of the object will yield an associative
array where the keys are taken from the header row. Consider a CSV file
containing:

```csv
id|name|description
1|foo|Something to foo with.
2|bar|Something to bar with.
```

The following code:

```php
$csv = new \Ork\Csv\Reader([
    'file' => '/path/to/file',
    'delimiter' => '|',
]);
foreach ($csv as $row) {
    print_r($row);
}
```

Will output:

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

## Fetching a Single Column

You may also iterate over the values for just one column. If the file does not
have a header row, the column parameter should be the zero-indexed column
number. (This is zero-indexed to ensure that values referenced in this manner
match those referenced by the array index in the full-row technique.) If the
file does have a header row, the column parameter should be the name of the
column.

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
    // $value contains the value from the column
    // identified by the "name" header row.
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
that this loads the entire file into memory in one big hunk.

## Callbacks

Callback filters may be defined to post-process fields. After reading each row,
the list of callbacks will be processed in the order defined. Consider a CSV
file containing:

```csv
id|name|description
1|foo|Something to foo with.
2|bar|Something to bar with.
```

The following code:

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        'name' => 'strtoupper',
    ],
    'delimiter' => '|',
    'header' => true,
]);
foreach ($csv as $row) {
    echo $row['name'] . "\n";
}
```

Will output:

    FOO
    BAR

Callbacks are specified in the standard PHP fashion -- as the string name of a
function, or an array of object and method name. Multiple callbacks may be
specified as an array.

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        'field1' => 'strtolower',
        'field2' => 'Obj::method',
        'field3' => ['strtolower', 'Obj::method', [new Obj(), 'method']],
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

If the callback key starts with a slash `/` then it will be interpreted as a
regex and applied to all columns that match.

```php
$csv = new \Ork\Csv\Reader([
    'callbacks' => [
        '/^foo_/i' => [[new Obj(), 'method']],
        '/./' => [[new Obj(), 'method']],
    ],
]);
```

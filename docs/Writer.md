# \Ork\Csv\Writer

`\Ork\Csv\Writer` is a CSV writer.

## Configuration

`\Ork\Csv\Writer` uses [`\Ork\Core\ConfigurableTrait`](https://github.com/AlexHowansky/ork-core/wiki/ConfigurableTrait).
Its configuration attributes are as follows:

|Name|Type|Description|
|----|----|-----------|
|columns|array|The column names for the header row. If not provided, the keys from the first array passed to the write() method will be used.|
|delimiter|string|The field delimiter character. Defaults to comma: `,`|
|escape|string|The escape character. Defaults to blackslash: `\`|
|file|string|The file to process. Defaults to: `php://stdin`|
|header|bool|Whether the first row contains column names. Defaults to: `true`|
|quote|string|The quote character. Defaults to double quote: `"`|

## Usage

Simply pass an array to the `write()` method.

### Without Header Row

To create a file with no header row, pass `false` to the `header` configuration
parameter and provide an indexed array to the `write()` method:

```php
$csv = new \Ork\Csv\Writer([
    'file' => '/path/to/file',
    'header' => false,
]);
$csv->write([1, 2, 3, 4, 5]);
$csv->write([6, 7, 8, 9, 10]);
```

Generates:

```csv
1,2,3,4,5
6,7,8,9,10
```

### With Explicit Header Row

Pass a column list in the `columns` configuration parameter:

```php
$csv = new \Ork\Csv\Writer([
    'columns' => ['One', 'Two', 'Three', 'Four', 'Five'],
    'file' => '/path/to/file',
    'header' => true,
]);
$csv->write([1, 2, 3, 4, 5]);
$csv->write([6, 7, 8, 9, 10]);
```

Generates:

```csv
One,Two,Three,Four,Five
1,2,3,4,5
6,7,8,9,10
```

### With Implicit Header Row

Pass `true` in the `header` configuration parameter and provide an associative
array to the `write()` method. You may provide an associative array for every
row, only the keys from the first will be used for the header row:

```php
$csv = new \Ork\Csv\Writer([
    'file' => '/path/to/file',
    'header' => true,
]);
$csv->write([
    'One' => 1,
    'Two' => 2,
    'Three' => 3,
    'Four' => 4,
    'Five' => 5,
]);
$csv->write([
    'One' => 6,
    'Two' => 7,
    'Three' => 8,
    'Four' => 9,
    'Five' => 10,
]);

```

Generates:

```csv
One,Two,Three,Four,Five
1,2,3,4,5
6,7,8,9,10
```

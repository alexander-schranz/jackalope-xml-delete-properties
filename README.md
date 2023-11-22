# Jackalope Doctrine DBAL Analyse deletion of properties

The benchmark requires 2 files:

 - `var/props.xml` - the xml of the node where we want to remove properties (SELECT props FROM phpcr_nodes WHERE identifier = ?)
 - `var/props.csv` - the props names which we want remove from the given xml (per line one property name)

## Different commands:

```bash
php src/remover.php legacy
php src/remover.php single_dom_document
php src/remover.php single_dom_query
php src/remover.php single_dom_query_chunk
```

## Results

### ~70000 properties (~12.5MB) remove ~1700 props

Run on a MacBook Pro (16", 2021) Apple M1 Pro 32 GB:

| Command                | Time   | Memory |
|------------------------|--------|--------|
| legacy                 | 6m 29s | 48 MB  |
| single_dom_document    | 2m 25s | 35 MB  |
| single_dom_query       | 1m 30  | 37 MB  |
| single_dom_query_chunk | 1m 29  | 35 MB  |

### Required changes for improvements

#### A: Group Properties

The most important thing is that we remove all properties at once instead of calling `saveXML` after each property removal.

For this we mostly would require first group all `deleteProperties` by its `node`:

```php
function groupByNode($deletePropertyPaths): array {
    $grouped = [];
    foreach ($deletePropertyPaths as $path) {
        $nodePath = PathHelper::getParentPath($path);
        $propertyName = PathHelper::getNodeName($path);

        $grouped[$nodePath][] = $propertyName;
    }

    return $grouped;
}
```

Then we load the single `node` remove all the properties and save the xml once via `saveXML`.

#### B: Grouped Reference delete queries

The `queries` to remove references should also be grouped and best a single query be send to delete the references
instead of one query per reference.

The queries are currently ignored in the benchmark as it is focused on XML manipulation.

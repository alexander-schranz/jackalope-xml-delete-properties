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
php src/remover.php xml_parse
```

## Results

### ~70000 properties (~12.5MB) remove ~1700 props

Run on a MacBook Pro (16", 2021) Apple M1 Pro 32 GB:

| Command                | Time   | Memory |
|------------------------|--------|--------|
| legacy                 | 6m 29s | 48 MB  |
| single_dom_document    | 2m 25s | 35 MB  |
| single_dom_query       | 1m 30s | 37 MB  |
| single_dom_query_chunk | 1m 29s | 35 MB  |
| xml_parse              | ~1s    | 35 MB  |

`legacy`: is the `1.9.0` version: https://github.com/jackalope/jackalope-doctrine-dbal/blob/f7b286f388e0d3a42497c29e597756d6e346fea5/src/Jackalope/Transport/DoctrineDBAL/Client.php#L1804
`single_dom_document`: should represent the state of `2.0.0-beta2` version after: https://github.com/jackalope/jackalope-doctrine-dbal/pull/423/files

**Blackfire**

I did not use Blackfire for benchmarking as it did show in past benchmark where `xml_parse`
can not be good profiled as having a lot of callback method being called via `xml_set_element_handler` and `xml_set_character_data_handler`.
So profiling takes more time as processing things as Blackfire need to log every method call.
Instead I depend on running real examples.

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

#### C: Replace DOMDocument with xml_parse

DOMDocument is bad for performance and should be avoided.
The `xml_parse` as it allows us to streamed reading the xml and skip the properties which we want to remove.
The [`XmlPropsRemover`](src/XmlPropsRemover.php) is an example how this could be done.

#### D: TODO

 - [ ] xml_parse escape attributes name correctly
 - [ ] xml_parse escape attributes value correctly
 - [ ] xml_parse data content correctly
 - [ ] xml_parse add queries to remove references
